<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\PdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RekapDaftarHadirController extends Controller
{
    private const ATURAN = [
        'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
        'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
        'dari' => ['required', 'date_format:Y-m-d'],
        'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
    ];

    // GET /api/rekap/daftar-hadir?mapel_id=1&kelas_id=2&dari=2026-11-01&sampai=2026-11-30
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->susun($request));
    }

    // GET /api/rekap/daftar-hadir/pdf?...
    public function pdf(Request $request)
    {
        $rekap = $this->susun($request);
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $pdf = Pdf::loadView('pdf.daftar-hadir', array_merge(PdfData::umum(mendatar: true), [
            'rekap' => $rekap,
            'ta' => $ta,
        ]))->setPaper('a4', 'landscape');

        return $pdf->stream(
            'daftar-hadir-' . Str::slug($rekap['mapel']['nama']) . '-' . Str::slug($rekap['kelas']['nama'])
            . '-' . $rekap['periode']['dari'] . '-' . $rekap['periode']['sampai'] . '.pdf'
        );
    }

    private function susun(Request $request): array
    {
        $data = $request->validate(self::ATURAN);
        $user = $request->user();

        $dari = Carbon::createFromFormat('Y-m-d', $data['dari'])->startOfDay();
        $sampai = Carbon::createFromFormat('Y-m-d', $data['sampai'])->startOfDay();

        abort_if($dari->diffInDays($sampai) > 400, 422, 'Rentang tanggal maksimal sekitar satu tahun.');

        $mapel = Mapel::findOrFail($data['mapel_id']);
        $kelas = Kelas::with('jurusan:id,nama')->findOrFail($data['kelas_id']);

        // Tanggal pertemuan = tanggal jurnal guru ini untuk mapel dan kelas tersebut
        $tanggal = Jurnal::where('guru_id', $user->id)
            ->where('kelas_id', $kelas->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->whereHas('jadwal', fn ($q) => $q->where('mapel_id', $mapel->id))
            ->orderBy('tanggal')
            ->get(['tanggal'])
            ->map(fn (Jurnal $j) => $j->tanggal->toDateString())
            ->unique()
            ->values();

        $siswa = Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->where('kelas_siswa.kelas_id', $kelas->id)
            ->where('siswa.is_active', true)
            ->orderBy('siswa.nama')
            ->get(['siswa.id', 'siswa.nama', 'siswa.jenis_kelamin']);

        // Peta [siswa][tanggal] => status dari absensi harian
        $peta = [];

        Absensi::where('kelas_id', $kelas->id)
            ->whereIn('tanggal', $tanggal->all())
            ->get(['siswa_id', 'tanggal', 'status'])
            ->each(function (Absensi $a) use (&$peta) {
                $peta[$a->siswa_id][$a->tanggal->toDateString()] = $a->status;
            });

        $kode = ['hadir' => 'H', 'sakit' => 'S', 'izin' => 'I', 'alpa' => 'A'];

        $baris = $siswa->map(function ($s) use ($tanggal, $peta, $kode) {
            $status = $tanggal->map(fn (string $t) => $kode[$peta[$s->id][$t] ?? ''] ?? '')->all();

            return [
                'id' => $s->id,
                'nama' => $s->nama,
                'jk' => $s->jenis_kelamin,
                'status' => $status,
                'hadir' => count(array_keys($status, 'H')),
                'sakit' => count(array_keys($status, 'S')),
                'izin' => count(array_keys($status, 'I')),
                'alpa' => count(array_keys($status, 'A')),
            ];
        })->values();

        // Jumlah hadir dan tidak hadir per pertemuan
        $kolom = $tanggal->map(function (string $t, int $i) use ($baris) {
            $tanda = $baris->pluck('status')->map(fn (array $s) => $s[$i] ?? '');

            return [
                'tanggal' => $t,
                'label' => Carbon::parse($t)->format('j/n'), // 9/11
                'hadir' => $tanda->filter(fn ($x) => $x === 'H')->count(),
                'tidak_hadir' => $tanda->filter(fn ($x) => in_array($x, ['S', 'I', 'A'], true))->count(),
            ];
        })->values();

        $totalHadir = $baris->sum('hadir');
        $totalTercatat = $totalHadir + $baris->sum('sakit') + $baris->sum('izin') + $baris->sum('alpa');

        return [
            'guru' => ['nama' => $user->name, 'nip' => $user->nip],
            'mapel' => ['id' => $mapel->id, 'nama' => $mapel->nama],
            'kelas' => [
                'id' => $kelas->id,
                'nama' => $kelas->nama,
                'konsentrasi' => $kelas->jurusan?->nama,
            ],
            'periode' => [
                'dari' => $dari->toDateString(),
                'sampai' => $sampai->toDateString(),
                'label' => $dari->copy()->locale('id')->translatedFormat('j F Y')
                    . ' s.d. ' . $sampai->copy()->locale('id')->translatedFormat('j F Y'),
            ],
            'semester' => $this->semester($dari, $sampai),
            'kolom' => $kolom->all(),
            'siswa' => $baris->all(),
            'ringkasan' => [
                'pertemuan' => $kolom->count(),
                'jumlah_siswa' => $baris->count(),
                'rata_kehadiran' => $totalTercatat > 0 ? (int) round($totalHadir / $totalTercatat * 100) : null,
            ],
        ];
    }

    // Juli sampai Desember = Ganjil, Januari sampai Juni = Genap
    private function semester(Carbon $dari, Carbon $sampai): string
    {
        $semester = fn (Carbon $d) => $d->month >= 7 ? 'Ganjil' : 'Genap';

        return $semester($dari) === $semester($sampai) ? $semester($dari) : 'Ganjil dan Genap';
    }
}