<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\PdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RekapSholatController extends Controller
{
    // Peran yang boleh melihat rekap sholat semua kelas; selain itu hanya wali kelas yang bersangkutan
    private const PERAN_SEMUA = ['admin', 'kesiswaan', 'kepala_sekolah'];

    // GET /api/rekap/sholat/kelas
    // Kelas yang boleh direkap oleh pengguna ini, beserta batas tahun ajaran
    public function kelas(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $user = $request->user();

        $kelas = Kelas::where('tahun_ajaran_id', $ta->id)
            ->when(
                ! in_array($user->role, self::PERAN_SEMUA, true),
                fn ($q) => $q->where('wali_kelas_id', $user->id)
            )
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return response()->json([
            'tahun_ajaran' => [
                'nama' => $ta->nama,
                'tanggal_mulai' => $ta->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $ta->tanggal_selesai->toDateString(),
            ],
            'data' => $kelas,
        ]);
    }

    // GET /api/rekap/sholat?kelas_id=1&dari=2026-07-13&sampai=2027-06-30
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->susun($request));
    }

    // GET /api/rekap/sholat/pdf?...
    public function pdf(Request $request)
    {
        $rekap = $this->susun($request);
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $pdf = Pdf::loadView('pdf.rekap-sholat', array_merge(PdfData::umum(), [
            'rekap' => $rekap,
            'ta' => $ta,
            'pencetak' => $request->user(),
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream(
            'rekap-sholat-' . Str::slug($rekap['kelas']['nama']) . '-' . $rekap['periode']['dari'] . '-' . $rekap['periode']['sampai'] . '.pdf'
        );
    }

    private function susun(Request $request): array
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);

        $user = $request->user();
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $dari = Carbon::createFromFormat('Y-m-d', $data['dari'])->startOfDay();
        $sampai = Carbon::createFromFormat('Y-m-d', $data['sampai'])->startOfDay();

        abort_if($dari->diffInDays($sampai) > 400, 422, 'Rentang tanggal maksimal sekitar satu tahun.');

        $kelas = Kelas::with('waliKelas:id,name,nip')
            ->where('tahun_ajaran_id', $ta->id)
            ->findOrFail($data['kelas_id']);

        abort_unless(
            in_array($user->role, self::PERAN_SEMUA, true) || (int) $kelas->wali_kelas_id === (int) $user->id,
            403,
            'Rekap sholat hanya untuk wali kelas, kesiswaan, kepala sekolah, dan admin.'
        );

        // Peserta sholat: siswa aktif beragama Islam
        $siswa = Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->where('kelas_siswa.kelas_id', $kelas->id)
            ->where('siswa.is_active', true)
            ->where('siswa.agama', 'Islam')
            ->orderBy('siswa.nama')
            ->get(['siswa.id', 'siswa.nis', 'siswa.nama', 'siswa.jenis_kelamin']);

        $ids = $siswa->pluck('id')->all();
        $rentang = [$dari->toDateString(), $sampai->toDateString()];

        $catatan = DB::table('absensi_sholat')
            ->where('kelas_id', $kelas->id)
            ->whereBetween('tanggal', $rentang)
            ->get(['siswa_id', 'tanggal', 'waktu', 'status'])
            ->whereIn('siswa_id', $ids)
            ->values();

        $hitung = fn ($koleksi, string $waktu, string $status) => $koleksi
            ->where('waktu', $waktu)
            ->where('status', $status)
            ->count();

        // Persentase kehadiran: berhalangan tidak dihitung
        $persen = fn (int $hadir, int $tidakHadir) => ($hadir + $tidakHadir) > 0
            ? (int) round($hadir / ($hadir + $tidakHadir) * 100)
            : null;

        $perSiswa = $catatan->groupBy('siswa_id');

        $baris = $siswa->map(function ($s) use ($perSiswa, $hitung, $persen) {
            $g = $perSiswa->get($s->id, collect());

            $rinci = fn (string $waktu) => [
                'hadir' => $hitung($g, $waktu, 'hadir'),
                'tidak_hadir' => $hitung($g, $waktu, 'tidak_hadir'),
                'berhalangan' => $hitung($g, $waktu, 'berhalangan'),
            ];

            $dhuhur = $rinci('dhuhur');
            $ashar = $rinci('ashar');
            $hadir = $dhuhur['hadir'] + $ashar['hadir'];
            $wajib = $hadir + $dhuhur['tidak_hadir'] + $ashar['tidak_hadir'];

            return [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'jk' => $s->jenis_kelamin,
                'dhuhur' => $dhuhur,
                'ashar' => $ashar,
                'hadir' => $hadir,
                'wajib' => $wajib,
                'persen' => $persen($hadir, $wajib - $hadir),
            ];
        })->values();

        $waktu = [];

        foreach (['dhuhur', 'ashar'] as $w) {
            $waktu[$w] = [
                'hadir' => $hitung($catatan, $w, 'hadir'),
                'tidak_hadir' => $hitung($catatan, $w, 'tidak_hadir'),
                'berhalangan' => $hitung($catatan, $w, 'berhalangan'),
            ];
        }

        $totalHadir = $waktu['dhuhur']['hadir'] + $waktu['ashar']['hadir'];
        $totalTidak = $waktu['dhuhur']['tidak_hadir'] + $waktu['ashar']['tidak_hadir'];

        // Tren per bulan (persentase hadir, gabungan Dhuhur dan Ashar)
        $perBulanData = $catatan->groupBy(fn ($r) => substr((string) $r->tanggal, 0, 7));
        $perBulan = [];

        for ($bulan = $dari->copy()->startOfMonth(); $bulan <= $sampai; $bulan->addMonth()) {
            $g = $perBulanData->get($bulan->format('Y-m'), collect());
            $h = $g->where('status', 'hadir')->count();
            $t = $g->where('status', 'tidak_hadir')->count();

            $perBulan[] = [
                'label' => $bulan->copy()->locale('id')->translatedFormat('M Y'),
                'hadir' => $h,
                'tidak_hadir' => $t,
                'persen' => $persen($h, $t),
            ];
        }

        $terbanyak = $baris
            ->map(fn (array $b) => ['nama' => $b['nama'], 'jumlah' => $b['dhuhur']['tidak_hadir'] + $b['ashar']['tidak_hadir']])
            ->filter(fn (array $b) => $b['jumlah'] > 0)
            ->sortByDesc('jumlah')
            ->take(5)
            ->values();

        return [
            'kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama],
            'wali' => $kelas->waliKelas ? ['nama' => $kelas->waliKelas->name, 'nip' => $kelas->waliKelas->nip] : null,
            'tahun_ajaran' => $ta->nama,
            'periode' => [
                'dari' => $rentang[0],
                'sampai' => $rentang[1],
                'label' => $dari->copy()->locale('id')->translatedFormat('j F Y')
                    . ' s.d. ' . $sampai->copy()->locale('id')->translatedFormat('j F Y'),
            ],
            'ringkasan' => [
                'peserta' => $siswa->count(),
                'hari_tercatat' => $catatan->pluck('tanggal')->unique()->count(),
                'persen' => $persen($totalHadir, $totalTidak),
                'persen_dhuhur' => $persen($waktu['dhuhur']['hadir'], $waktu['dhuhur']['tidak_hadir']),
                'persen_ashar' => $persen($waktu['ashar']['hadir'], $waktu['ashar']['tidak_hadir']),
            ],
            'waktu' => $waktu,
            'per_bulan' => $perBulan,
            'terbanyak_tidak_hadir' => $terbanyak->all(),
            'siswa' => $baris->all(),
        ];
    }
}