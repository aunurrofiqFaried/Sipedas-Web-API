<?php

namespace App\Http\Controllers\Api\Pimpinan;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\AbsensiSholat;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KehadiranKelasController extends Controller
{
    // GET /api/pimpinan/kehadiran?tanggal=2026-11-16
    // Absensi dan sholat seluruh kelas pada satu tanggal
    public function index(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $tanggal = $this->tentukanTanggal($request);
        $tgl = $tanggal->toDateString();

        $kelas = DB::table('kelas as k')
            ->leftJoin('jurusan as j', 'j.id', '=', 'k.jurusan_id')
            ->leftJoin('users as u', 'u.id', '=', 'k.wali_kelas_id')
            ->where('k.tahun_ajaran_id', $ta->id)
            ->orderBy('k.nama')
            ->get(['k.id', 'k.nama', 'j.nama as jurusan', 'u.name as wali']);

        $siswa = DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->where('ks.tahun_ajaran_id', $ta->id)
            ->where('s.is_active', true)
            ->whereIn('ks.kelas_id', $kelas->pluck('id'))
            ->groupBy('ks.kelas_id')
            ->selectRaw("ks.kelas_id as kelas_id, COUNT(*) as total, SUM(CASE WHEN s.agama = 'Islam' THEN 1 ELSE 0 END) as muslim")
            ->get()
            ->keyBy('kelas_id');

        // Kelas tanpa siswa aktif dilewati
        $kelasAktif = $kelas->filter(fn ($k) => (int) ($siswa->get($k->id)->total ?? 0) > 0)->values();
        $idKelas = $kelasAktif->pluck('id');

        $absensi = DB::table('absensi')
            ->whereIn('kelas_id', $idKelas)
            ->where('tanggal', $tgl)
            ->selectRaw('kelas_id, status, COUNT(*) as total')
            ->groupBy('kelas_id', 'status')
            ->get()
            ->groupBy('kelas_id');

        $sholat = DB::table('absensi_sholat')
            ->whereIn('kelas_id', $idKelas)
            ->where('tanggal', $tgl)
            ->selectRaw('kelas_id, waktu, status, COUNT(*) as total')
            ->groupBy('kelas_id', 'waktu', 'status')
            ->get()
            ->groupBy('kelas_id');

        // Siapa yang mengabsen tiap kelas hari itu
        $penginput = DB::table('absensi as a')
            ->join('users as u', 'u.id', '=', 'a.diinput_oleh')
            ->whereIn('a.kelas_id', $idKelas)
            ->where('a.tanggal', $tgl)
            ->groupBy('a.kelas_id', 'u.name')
            ->get(['a.kelas_id', 'u.name'])
            ->groupBy('kelas_id')
            ->map(fn (Collection $g) => $g->pluck('name')->first());

        $baris = $kelasAktif->map(function ($k) use ($siswa, $absensi, $sholat, $penginput) {
            $jumlah = (int) $siswa->get($k->id)->total;
            $absen = $this->hitungAbsensi($absensi->get($k->id, collect()));
            $status = $absen['tercatat'] === 0 ? 'belum' : ($absen['tercatat'] < $jumlah ? 'sebagian' : 'lengkap');
            $s = $sholat->get($k->id, collect());

            return [
                'id' => $k->id,
                'nama' => $k->nama,
                'jurusan' => $k->jurusan ?? 'Tanpa jurusan',
                'wali_kelas' => $k->wali,
                'siswa' => $jumlah,
                'absensi' => $absen + [
                    'status' => $status,
                    'diabsen_oleh' => $penginput->get($k->id),
                ],
                'sholat' => [
                    'peserta' => (int) $siswa->get($k->id)->muslim,
                    'dhuhur' => $this->hitungSholat($s, 'dhuhur'),
                    'ashar' => $this->hitungSholat($s, 'ashar'),
                ],
            ];
        })->values();

        $totalSiswa = (int) $baris->sum('siswa');
        $tercatat = (int) $baris->sum('absensi.tercatat');
        $hadir = (int) $baris->sum('absensi.hadir');

        $ringkasSholat = fn (string $waktu) => [
            'hadir' => (int) $baris->sum("sholat.$waktu.hadir"),
            'tidak_hadir' => (int) $baris->sum("sholat.$waktu.tidak_hadir"),
            'berhalangan' => (int) $baris->sum("sholat.$waktu.berhalangan"),
            'persen' => $this->persen(
                (int) $baris->sum("sholat.$waktu.hadir"),
                (int) $baris->sum("sholat.$waktu.hadir") + (int) $baris->sum("sholat.$waktu.tidak_hadir"),
            ),
            'kelas' => $baris->filter(fn (array $b) => $b['sholat'][$waktu]['tercatat'] > 0)->count(),
        ];

        $libur = KalenderAkademik::liburPada($tanggal);

        return response()->json([
            'tanggal' => $tgl,
            'label' => $tanggal->copy()->locale('id')->translatedFormat('l, j F Y'),
            'hari_ini' => $tanggal->isSameDay(now()),
            'libur' => $libur?->judul,
            'ringkasan' => [
                'kelas' => $baris->count(),
                'siswa' => $totalSiswa,
                'tercatat' => $tercatat,
                'hadir' => $hadir,
                'sakit' => (int) $baris->sum('absensi.sakit'),
                'izin' => (int) $baris->sum('absensi.izin'),
                'alpa' => (int) $baris->sum('absensi.alpa'),
                'belum' => max(0, $totalSiswa - $tercatat),
                'persen' => $this->persen($hadir, $tercatat),
                'kelas_lengkap' => $baris->where('absensi.status', 'lengkap')->count(),
                'kelas_sebagian' => $baris->where('absensi.status', 'sebagian')->count(),
                'kelas_belum' => $baris->where('absensi.status', 'belum')->count(),
            ],
            'sholat' => [
                'peserta' => (int) $baris->sum('sholat.peserta'),
                'dhuhur' => $ringkasSholat('dhuhur'),
                'ashar' => $ringkasSholat('ashar'),
            ],
            'kelas' => $baris->all(),
        ]);
    }

    // GET /api/pimpinan/kehadiran/{kelas}?tanggal=2026-11-16
    // Rincian satu kelas: status absensi dan sholat tiap siswa
    public function show(Request $request, Kelas $kelas): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        abort_unless((int) $kelas->tahun_ajaran_id === (int) $ta->id, 404);

        $tanggal = $this->tentukanTanggal($request);
        $tgl = $tanggal->toDateString();

        $absensi = Absensi::where('kelas_id', $kelas->id)->where('tanggal', $tgl)->get()->keyBy('siswa_id');

        $sholat = AbsensiSholat::where('kelas_id', $kelas->id)
            ->where('tanggal', $tgl)
            ->get()
            ->groupBy('siswa_id')
            ->map(fn (Collection $g) => $g->keyBy('waktu'));

        $siswa = DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->where('ks.kelas_id', $kelas->id)
            ->where('ks.tahun_ajaran_id', $ta->id)
            ->where('s.is_active', true)
            ->orderBy('s.nama')
            ->get(['s.id', 's.nis', 's.nama', 's.jenis_kelamin', 's.agama']);

        $daftar = $siswa->map(function ($s) use ($absensi, $sholat) {
            $muslim = $s->agama === 'Islam';
            $sh = $sholat->get($s->id, collect());

            return [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'jk' => $s->jenis_kelamin,
                'status' => $absensi->get($s->id)?->status,
                'keterangan' => $absensi->get($s->id)?->keterangan,
                'ikut_sholat' => $muslim,
                'dhuhur' => $muslim ? $sh->get('dhuhur')?->status : null,
                'ashar' => $muslim ? $sh->get('ashar')?->status : null,
            ];
        })->values();

        $hitung = fn (string $kunci, string $nilai) => $daftar->where($kunci, $nilai)->count();

        $peserta = $daftar->where('ikut_sholat', true);
        $ringkasSholat = fn (string $waktu) => [
            'hadir' => $peserta->where($waktu, 'hadir')->count(),
            'tidak_hadir' => $peserta->where($waktu, 'tidak_hadir')->count(),
            'berhalangan' => $peserta->where($waktu, 'berhalangan')->count(),
            'belum' => $peserta->whereNull($waktu)->count(),
        ];

        $tercatat = $daftar->whereNotNull('status')->count();
        $hadir = $hitung('status', 'hadir');
        $kelas->load('waliKelas:id,name');

        return response()->json([
            'tanggal' => $tgl,
            'label' => $tanggal->copy()->locale('id')->translatedFormat('l, j F Y'),
            'libur' => KalenderAkademik::liburPada($tanggal)?->judul,
            'kelas' => [
                'id' => $kelas->id,
                'nama' => $kelas->nama,
                'wali_kelas' => $kelas->waliKelas?->name,
                'siswa' => $daftar->count(),
            ],
            'absensi' => [
                'tercatat' => $tercatat,
                'hadir' => $hadir,
                'sakit' => $hitung('status', 'sakit'),
                'izin' => $hitung('status', 'izin'),
                'alpa' => $hitung('status', 'alpa'),
                'belum' => $daftar->count() - $tercatat,
                'persen' => $this->persen($hadir, $tercatat),
            ],
            'sholat' => [
                'peserta' => $peserta->count(),
                'dhuhur' => $ringkasSholat('dhuhur'),
                'ashar' => $ringkasSholat('ashar'),
            ],
            'siswa' => $daftar->all(),
        ]);
    }

    // Tanggal dari permintaan; bila kosong, hari efektif terakhir (hari ini kalau bukan libur)
    private function tentukanTanggal(Request $request): Carbon
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        if (! empty($data['tanggal'])) {
            return Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay();
        }

        $t = now()->startOfDay();

        for ($i = 0; $i < 14 && KalenderAkademik::liburPada($t); $i++) {
            $t->subDay();
        }

        return $t;
    }

    private function persen(int $a, int $b): ?int
    {
        return $b > 0 ? (int) round($a / $b * 100) : null;
    }

    private function hitungAbsensi(Collection $g): array
    {
        $n = fn (string $s) => (int) $g->where('status', $s)->sum('total');
        $tercatat = (int) $g->sum('total');

        return [
            'tercatat' => $tercatat,
            'hadir' => $n('hadir'),
            'sakit' => $n('sakit'),
            'izin' => $n('izin'),
            'alpa' => $n('alpa'),
            'persen' => $this->persen($n('hadir'), $tercatat),
        ];
    }

    private function hitungSholat(Collection $g, string $waktu): array
    {
        $w = $g->where('waktu', $waktu);
        $n = fn (string $s) => (int) $w->where('status', $s)->sum('total');

        return [
            'tercatat' => (int) $w->sum('total'),
            'hadir' => $n('hadir'),
            'tidak_hadir' => $n('tidak_hadir'),
            'berhalangan' => $n('berhalangan'),
            'persen' => $this->persen($n('hadir'), $n('hadir') + $n('tidak_hadir')),
        ];
    }
}