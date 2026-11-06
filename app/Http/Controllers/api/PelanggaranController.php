<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPelanggaran;
use App\Models\PelanggaranSiswa;
use App\Models\Siswa;
use App\Models\SpSiswa;
use App\Models\TahunAjaran;
use App\Services\PelanggaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\RemisiSiswa;

class PelanggaranController extends Controller
{
    // GET /api/pelanggaran/jenis
    public function jenis(): JsonResponse
    {
        $data = JenisPelanggaran::where('is_active', true)
            ->orderBy('kategori')
            ->orderBy('poin')
            ->get(['id', 'nama', 'kategori', 'poin']);

        return response()->json(['data' => $data]);
    }

    // GET /api/siswa/cari?q=budi
    public function cariSiswa(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $q = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($q) < 2, 422, 'Ketik minimal 2 karakter.');

        $data = Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->join('kelas', 'kelas.id', '=', 'kelas_siswa.kelas_id')
            ->where('kelas_siswa.tahun_ajaran_id', $ta->id)
            ->where('siswa.is_active', true)
            ->where(function ($w) use ($q) {
                $w->where('siswa.nama', 'like', "%{$q}%")
                    ->orWhere('siswa.nis', 'like', "{$q}%");
            })
            ->orderBy('siswa.nama')
            ->limit(20)
            ->get(['siswa.id', 'siswa.nis', 'siswa.nama', 'kelas.nama as kelas']);

        return response()->json(['data' => $data]);
    }

    // POST /api/pelanggaran
    public function store(Request $request, PelanggaranService $service): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'jenis_pelanggaran_id' => [
                'required', 'integer',
                Rule::exists('jenis_pelanggaran', 'id')->where('is_active', true),
            ],
            'tanggal' => [
                'nullable', 'date',
                'before_or_equal:today',
                'after_or_equal:' . $ta->tanggal_mulai->toDateString(),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $siswa = Siswa::findOrFail($data['siswa_id']);

        $terdaftar = DB::table('kelas_siswa')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->exists();
        abort_unless($terdaftar, 422, 'Siswa tidak terdaftar di tahun ajaran aktif.');

        $jenis = JenisPelanggaran::findOrFail($data['jenis_pelanggaran_id']);

        $hasil = $service->catat(
            $request->user(),
            $siswa,
            $jenis,
            $ta,
            $data['tanggal'] ?? now()->toDateString(),
            $data['keterangan'] ?? null
        );

        return response()->json([
            'message' => 'Pelanggaran tercatat.',
            'poin_ditambahkan' => $hasil['pelanggaran']->poin,
            'total_poin' => $hasil['total_poin'],
            'sp_baru' => $hasil['sp_baru']?->load('aturan:id,nama,level,batas_poin'),
        ], 201);
    }

    // GET /api/siswa/{siswa}/pelanggaran
    public function riwayat(Siswa $siswa): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $riwayat = PelanggaranSiswa::with(['jenis:id,nama', 'pencatat:id,name'])
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        $remisi = RemisiSiswa::with(['jenis:id,nama', 'pencatat:id,name'])
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        $sp = SpSiswa::with('aturan:id,nama,level,batas_poin')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->get();

        $totalPelanggaran = (int) $riwayat->sum('poin');
        $totalRemisi = (int) $remisi->sum('poin');

        return response()->json([
            'siswa' => $siswa->only(['id', 'nis', 'nama']),
            'tahun_ajaran' => $ta->nama,
            'total_pelanggaran' => $totalPelanggaran,
            'total_remisi' => $totalRemisi,
            'total_poin' => max(0, $totalPelanggaran - $totalRemisi),
            'sp' => $sp,
            'riwayat' => $riwayat,
            'remisi' => $remisi,
        ]);
    }
}
