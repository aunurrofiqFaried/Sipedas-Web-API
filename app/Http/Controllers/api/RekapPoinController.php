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

class RekapPoinController extends Controller
{
    private const ATURAN = [
        'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
        'dari' => ['required', 'date_format:Y-m-d'],
        'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
        'sertakan_nol' => ['nullable', 'boolean'],
    ];

    // GET /api/rekap/poin?kelas_id=1&dari=2026-07-13&sampai=2027-06-30&sertakan_nol=0
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->susun($request));
    }

    // GET /api/rekap/poin/pdf?...
    public function pdf(Request $request)
    {
        $rekap = $this->susun($request);
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $pdf = Pdf::loadView('pdf.rekap-poin', array_merge(PdfData::umum(), [
            'rekap' => $rekap,
            'ta' => $ta,
            'pencetak' => $request->user(),
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream(
            'rekap-poin-' . Str::slug($rekap['kelas']['nama']) . '-' . $rekap['periode']['dari'] . '-' . $rekap['periode']['sampai'] . '.pdf'
        );
    }

    private function susun(Request $request): array
    {
        $data = $request->validate(self::ATURAN);
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $dari = Carbon::createFromFormat('Y-m-d', $data['dari'])->startOfDay();
        $sampai = Carbon::createFromFormat('Y-m-d', $data['sampai'])->startOfDay();

        abort_if($dari->diffInDays($sampai) > 400, 422, 'Rentang tanggal maksimal sekitar satu tahun.');

        $kelas = Kelas::with('waliKelas:id,name,nip')
            ->where('tahun_ajaran_id', $ta->id)
            ->findOrFail($data['kelas_id']);

        $siswa = Siswa::query()
            ->join('kelas_siswa', 'kelas_siswa.siswa_id', '=', 'siswa.id')
            ->where('kelas_siswa.kelas_id', $kelas->id)
            ->where('siswa.is_active', true)
            ->orderBy('siswa.nama')
            ->get(['siswa.id', 'siswa.nis', 'siswa.nama']);

        $ids = $siswa->pluck('id')->all();
        $rentang = [$dari->toDateString(), $sampai->toDateString()];

        // Pelanggaran dan remisi pada periode terpilih
        $pelanggaran = DB::table('pelanggaran_siswa as p')
            ->join('jenis_pelanggaran as j', 'j.id', '=', 'p.jenis_pelanggaran_id')
            ->whereIn('p.siswa_id', $ids)
            ->whereBetween('p.tanggal', $rentang)
            ->get(['p.siswa_id', 'p.poin', 'p.tanggal', 'j.nama as jenis_nama', 'j.kategori']);

        $remisi = DB::table('remisi_siswa')
            ->whereIn('siswa_id', $ids)
            ->whereBetween('tanggal', $rentang)
            ->get(['siswa_id', 'poin']);

        $aturan = DB::table('aturan_sp')->orderBy('level')->get(['level', 'nama', 'batas_poin']);

        // SP tertinggi (selain yang ditolak) milik tiap siswa pada tahun ajaran berjalan
        $sp = DB::table('sp_siswa as s')
            ->join('aturan_sp as a', 'a.id', '=', 's.aturan_sp_id')
            ->whereIn('s.siswa_id', $ids)
            ->where('s.status', '!=', 'ditolak')
            ->whereBetween('s.created_at', [
                $ta->tanggal_mulai->copy()->startOfDay(),
                $ta->tanggal_selesai->copy()->endOfDay(),
            ])
            ->get(['s.siswa_id', 's.status', 'a.nama', 'a.level'])
            ->groupBy('siswa_id')
            ->map(fn ($g) => $g->sortByDesc('level')->first());

        $pelPerSiswa = $pelanggaran->groupBy('siswa_id');
        $remPerSiswa = $remisi->groupBy('siswa_id');

        $semuaBaris = $siswa->map(function ($s) use ($pelPerSiswa, $remPerSiswa, $aturan, $sp) {
            $p = $pelPerSiswa->get($s->id, collect());
            $poinPelanggaran = (int) $p->sum('poin');
            $poinRemisi = (int) $remPerSiswa->get($s->id, collect())->sum('poin');
            $aktif = max(0, $poinPelanggaran - $poinRemisi);

            // Level ambang tertinggi yang sudah terlewati
            $level = 0;
            foreach ($aturan as $a) {
                if ($aktif >= $a->batas_poin) {
                    $level = (int) $a->level;
                }
            }

            $spSiswa = $sp->get($s->id);

            return [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'kejadian' => $p->count(),
                'poin_pelanggaran' => $poinPelanggaran,
                'remisi' => $poinRemisi,
                'poin_aktif' => $aktif,
                'level' => $level,
                'sp' => $spSiswa
                    ? ['nama' => $spSiswa->nama, 'level' => (int) $spSiswa->level, 'status' => $spSiswa->status]
                    : null,
            ];
        });

        $baris = $semuaBaris
            ->when(! $request->boolean('sertakan_nol'), fn ($c) => $c->filter(fn (array $b) => $b['kejadian'] > 0 || $b['remisi'] > 0))
            ->sort(fn (array $a, array $b) => [$b['poin_aktif'], $b['poin_pelanggaran'], $a['nama']] <=> [$a['poin_aktif'], $a['poin_pelanggaran'], $b['nama']])
            ->values();

        // Grafik: kategori, jenis terbanyak, tren per bulan
        $urutanKategori = ['ringan', 'sedang', 'berat', 'lainnya'];

        $kategori = $pelanggaran
            ->groupBy(fn ($r) => $r->kategori ?: 'lainnya')
            ->map(fn ($g, $k) => ['kategori' => $k, 'jumlah' => $g->count(), 'poin' => (int) $g->sum('poin')])
            ->sortBy(fn (array $x) => ($i = array_search($x['kategori'], $urutanKategori, true)) === false ? 9 : $i)
            ->values();

        $jenisTerbanyak = $pelanggaran
            ->groupBy('jenis_nama')
            ->map(fn ($g, $nama) => ['nama' => $nama, 'jumlah' => $g->count(), 'poin' => (int) $g->sum('poin')])
            ->sort(fn (array $a, array $b) => [$b['jumlah'], $b['poin']] <=> [$a['jumlah'], $a['poin']])
            ->take(8)
            ->values();

        $perBulanData = $pelanggaran->groupBy(fn ($r) => substr((string) $r->tanggal, 0, 7));
        $perBulan = [];

        for ($bulan = $dari->copy()->startOfMonth(); $bulan <= $sampai; $bulan->addMonth()) {
            $g = $perBulanData->get($bulan->format('Y-m'), collect());

            $perBulan[] = [
                'label' => $bulan->copy()->locale('id')->translatedFormat('M Y'),
                'jumlah' => $g->count(),
                'poin' => (int) $g->sum('poin'),
            ];
        }

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
            'aturan' => $aturan->map(fn ($a) => [
                'level' => (int) $a->level,
                'nama' => $a->nama,
                'batas_poin' => (int) $a->batas_poin,
            ])->all(),
            'ringkasan' => [
                'jumlah_siswa' => $siswa->count(),
                'kejadian' => $pelanggaran->count(),
                'poin_pelanggaran' => (int) $pelanggaran->sum('poin'),
                'remisi' => (int) $remisi->sum('poin'),
                'siswa_bermasalah' => $semuaBaris->filter(fn (array $b) => $b['kejadian'] > 0)->count(),
                'siswa_sp' => $semuaBaris->filter(fn (array $b) => $b['sp'] !== null)->count(),
            ],
            'kategori' => $kategori->all(),
            'jenis_terbanyak' => $jenisTerbanyak->all(),
            'per_bulan' => $perBulan,
            'siswa' => $baris->all(),
        ];
    }
}