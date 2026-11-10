<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\TahunAjaran;
use App\Support\PdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RekapJurnalController extends Controller
{
    private const ATURAN = [
        'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
        'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        'dari' => ['required', 'date_format:Y-m-d'],
        'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
    ];

    // GET /api/rekap/jurnal?mapel_id=1&kelas_id=2&dari=2026-11-01&sampai=2026-11-30
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->susun($request));
    }

    // GET /api/rekap/jurnal/pdf?...
    public function pdf(Request $request)
    {
        $rekap = $this->susun($request);
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $pdf = Pdf::loadView('pdf.jurnal-guru', array_merge(PdfData::umum(mendatar: true), [
            'rekap' => $rekap,
            'ta' => $ta,
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream(
            'jurnal-' . Str::slug($rekap['mapel']['nama']) . '-' . $rekap['periode']['dari'] . '-' . $rekap['periode']['sampai'] . '.pdf'
        );
    }

    // Perhitungan yang dipakai bersama oleh pratinjau (JSON) dan PDF
    private function susun(Request $request): array
    {
        $data = $request->validate(self::ATURAN);
        $user = $request->user();

        $dari = Carbon::createFromFormat('Y-m-d', $data['dari'])->startOfDay();
        $sampai = Carbon::createFromFormat('Y-m-d', $data['sampai'])->startOfDay();

        abort_if($dari->diffInDays($sampai) > 400, 422, 'Rentang tanggal maksimal sekitar satu tahun.');

        $mapel = Mapel::findOrFail($data['mapel_id']);
        $kelasFilter = ! empty($data['kelas_id']) ? Kelas::findOrFail($data['kelas_id']) : null;

        // Hanya jurnal milik guru yang login, untuk mapel dan periode yang dipilih
        $jurnal = Jurnal::with(['kelas.jurusan:id,nama', 'jadwal:id,mapel_id,jam_ke_mulai,jam_ke_selesai'])
            ->where('guru_id', $user->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->whereHas('jadwal', fn ($q) => $q->where('mapel_id', $mapel->id))
            ->when($kelasFilter, fn ($q) => $q->where('kelas_id', $kelasFilter->id))
            ->get()
            ->sortBy(fn (Jurnal $j) => $j->tanggal->toDateString() . sprintf('%02d', $j->jadwal?->jam_ke_mulai ?? 99))
            ->values();

        $idKelas = $jurnal->pluck('kelas_id')->unique()->values();

        // Absensi harian kelas pada tanggal-tanggal itu, dikelompokkan per kelas dan tanggal
        $presensi = Absensi::whereIn('kelas_id', $idKelas)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->with('siswa:id,nama')
            ->get()
            ->groupBy(fn (Absensi $a) => $a->kelas_id . '|' . $a->tanggal->toDateString());

        // Jumlah siswa aktif per kelas
        $ukuran = DB::table('kelas_siswa')
            ->join('siswa', 'siswa.id', '=', 'kelas_siswa.siswa_id')
            ->where('siswa.is_active', true)
            ->whereIn('kelas_siswa.kelas_id', $idKelas)
            ->groupBy('kelas_siswa.kelas_id')
            ->selectRaw('kelas_siswa.kelas_id as kelas_id, COUNT(*) as total')
            ->pluck('total', 'kelas_id');

        $baris = $jurnal->map(function (Jurnal $j) use ($presensi, $ukuran) {
            $catatan = $presensi->get($j->kelas_id . '|' . $j->tanggal->toDateString());
            $tidakHadir = $catatan ? $catatan->where('status', '!=', 'hadir') : collect();
            $tanggal = $j->tanggal->copy()->locale('id');

            return [
                'id' => $j->id,
                'tanggal' => $tanggal->toDateString(),
                'hari' => $tanggal->translatedFormat('l'),
                'tanggal_label' => $tanggal->translatedFormat('j M Y'),
                'kelas' => $j->kelas?->nama,
                'konsentrasi' => $j->kelas?->jurusan?->nama,
                'jam_ke_mulai' => $j->jadwal?->jam_ke_mulai,
                'jam_ke_selesai' => $j->jadwal?->jam_ke_selesai,
                'jumlah_siswa' => (int) ($ukuran[$j->kelas_id] ?? 0),
                'hadir' => $catatan ? $catatan->where('status', 'hadir')->count() : null,
                'tidak_hadir' => $catatan ? $tidakHadir->count() : null,
                // nama depan saja
                'nama_tidak_hadir' => $tidakHadir
                    ->map(fn (Absensi $a) => Str::before(trim($a->siswa?->nama ?? ''), ' '))
                    ->filter()
                    ->values()
                    ->all(),
                'materi' => $j->materi,
                'catatan' => $j->catatan,
            ];
        });

        $denganPresensi = $baris->filter(fn (array $b) => $b['hadir'] !== null && ($b['hadir'] + $b['tidak_hadir']) > 0);

        return [
            'guru' => ['nama' => $user->name, 'nip' => $user->nip],
            'mapel' => ['id' => $mapel->id, 'nama' => $mapel->nama],
            'kelas' => $kelasFilter ? ['id' => $kelasFilter->id, 'nama' => $kelasFilter->nama] : null,
            'periode' => [
                'dari' => $dari->toDateString(),
                'sampai' => $sampai->toDateString(),
                'label' => $dari->copy()->locale('id')->translatedFormat('j F Y')
                    . ' s.d. ' . $sampai->copy()->locale('id')->translatedFormat('j F Y'),
            ],
            'ringkasan' => [
                'pertemuan' => $baris->count(),
                'jam_pelajaran' => (int) $jurnal->sum(
                    fn (Jurnal $j) => $j->jadwal ? $j->jadwal->jam_ke_selesai - $j->jadwal->jam_ke_mulai + 1 : 0
                ),
                'rata_kehadiran' => $denganPresensi->isNotEmpty()
                    ? (int) round($denganPresensi->avg(fn (array $b) => $b['hadir'] / ($b['hadir'] + $b['tidak_hadir']) * 100))
                    : null,
            ],
            'baris' => $baris->all(),
        ];
    }
}