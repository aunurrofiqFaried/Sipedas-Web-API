<?php

namespace App\Support;

use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardPimpinan
{
    public static function susun(): array
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $sekarang = now();
        $hariIni = $sekarang->copy()->startOfDay();

        $persen = fn (int|float $a, int|float $b): ?int => $b > 0 ? (int) round($a / $b * 100) : null;

        // ---------- Hari efektif ----------
        $liburRows = DB::table('kalender_akademik')
            ->where('jenis', 'libur')
            ->where('tanggal_selesai', '>=', $hariIni->copy()->subDays(60)->toDateString())
            ->where('tanggal_mulai', '<=', $hariIni->toDateString())
            ->get(['judul', 'tanggal_mulai', 'tanggal_selesai']);

        $cariLibur = fn (string $t) => $liburRows->first(
            fn ($l) => substr((string) $l->tanggal_mulai, 0, 10) <= $t && substr((string) $l->tanggal_selesai, 0, 10) >= $t
        );

        $efektif = fn (Carbon $d) => $d->isoWeekday() <= 5 && ! $cariLibur($d->toDateString());

        // Data "hari ini" memakai hari efektif terakhir kalau hari ini libur
        $hariData = $hariIni->copy();

        for ($i = 0; $i < 14 && ! $efektif($hariData); $i++) {
            $hariData->subDay();
        }

        $tgl = $hariData->toDateString();
        $adalahHariIni = $hariData->isSameDay($hariIni);
        $libur = $efektif($hariIni) ? null : ($cariLibur($hariIni->toDateString())?->judul ?? 'Akhir pekan');

        // ---------- Kelas dan siswa ----------
        $kelas = DB::table('kelas as k')
            ->leftJoin('jurusan as j', 'j.id', '=', 'k.jurusan_id')
            ->where('k.tahun_ajaran_id', $ta->id)
            ->orderBy('k.nama')
            ->get(['k.id', 'k.nama', 'j.nama as jurusan']);

        $siswaPerKelas = DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->where('s.is_active', true)
            ->whereIn('ks.kelas_id', $kelas->pluck('id'))
            ->groupBy('ks.kelas_id')
            ->selectRaw('ks.kelas_id as kelas_id, COUNT(*) as total')
            ->pluck('total', 'kelas_id');

        // Kelas tanpa siswa aktif dilewati
        $kelasAktif = $kelas->filter(fn ($k) => (int) $siswaPerKelas->get($k->id, 0) > 0)->values();
        $idKelas = $kelasAktif->pluck('id');
        $totalSiswa = (int) $siswaPerKelas->sum();

        // ---------- Absensi hari ini ----------
        $hitungStatus = fn ($g, string $s) => (int) $g->where('status', $s)->sum('total');

        $absensiHari = DB::table('absensi')
            ->whereIn('kelas_id', $idKelas)
            ->where('tanggal', $tgl)
            ->selectRaw('kelas_id, status, COUNT(*) as total')
            ->groupBy('kelas_id', 'status')
            ->get()
            ->groupBy('kelas_id');

        $perKelas = $kelasAktif->map(function ($k) use ($absensiHari, $siswaPerKelas, $hitungStatus, $persen) {
            $g = $absensiHari->get($k->id, collect());
            $hadir = $hitungStatus($g, 'hadir');
            $tercatat = (int) $g->sum('total');

            return [
                'id' => $k->id,
                'nama' => $k->nama,
                'jurusan' => $k->jurusan ?? 'Tanpa jurusan',
                'siswa' => (int) $siswaPerKelas->get($k->id, 0),
                'tercatat' => $tercatat,
                'hadir' => $hadir,
                'sakit' => $hitungStatus($g, 'sakit'),
                'izin' => $hitungStatus($g, 'izin'),
                'alpa' => $hitungStatus($g, 'alpa'),
                'persen' => $persen($hadir, $tercatat),
            ];
        })->values();

        $tercatatTotal = (int) $perKelas->sum('tercatat');
        $hadirTotal = (int) $perKelas->sum('hadir');
        $persenHadir = $persen($hadirTotal, $tercatatTotal);

        // ---------- Tren 14 hari terakhir ----------
        $tren = DB::table('absensi')
            ->whereIn('kelas_id', $idKelas)
            ->whereBetween('tanggal', [$hariData->copy()->subDays(40)->toDateString(), $tgl])
            ->selectRaw('tanggal, status, COUNT(*) as total')
            ->groupBy('tanggal', 'status')
            ->get()
            ->groupBy(fn ($r) => substr((string) $r->tanggal, 0, 10))
            ->sortKeys()
            ->map(function ($g, $t) use ($hitungStatus, $persen) {
                $total = (int) $g->sum('total');
                $hadir = $hitungStatus($g, 'hadir');

                return [
                    'tanggal' => $t,
                    'label' => Carbon::parse($t)->locale('id')->translatedFormat('j M'),
                    'hadir' => $hadir,
                    'sakit' => $hitungStatus($g, 'sakit'),
                    'izin' => $hitungStatus($g, 'izin'),
                    'alpa' => $hitungStatus($g, 'alpa'),
                    'total' => $total,
                    'persen' => $persen($hadir, $total),
                ];
            })
            ->filter(fn (array $x) => $x['total'] > 0)
            ->values()
            ->take(-14)
            ->values();

        // ---------- Peta panas: kelas x 10 hari ----------
        $tanggalPeta = $tren->take(-10)->pluck('tanggal')->values();

        $petaRaw = DB::table('absensi')
            ->whereIn('kelas_id', $idKelas)
            ->whereIn('tanggal', $tanggalPeta->all())
            ->selectRaw('kelas_id, tanggal, status, COUNT(*) as total')
            ->groupBy('kelas_id', 'tanggal', 'status')
            ->get()
            ->groupBy(fn ($r) => $r->kelas_id . '|' . substr((string) $r->tanggal, 0, 10));

        $petaKelas = $kelasAktif->map(fn ($k) => [
            'id' => $k->id,
            'nama' => $k->nama,
            'jurusan' => $k->jurusan ?? 'Tanpa jurusan',
            'nilai' => $tanggalPeta->map(function ($t) use ($k, $petaRaw, $persen, $hitungStatus) {
                $g = $petaRaw->get($k->id . '|' . $t);

                return $g ? $persen($hitungStatus($g, 'hadir'), (int) $g->sum('total')) : null;
            })->all(),
        ])->values();

        // ---------- Sholat ----------
        $sholatRaw = DB::table('absensi_sholat')
            ->whereIn('kelas_id', $idKelas)
            ->where('tanggal', $tgl)
            ->selectRaw('waktu, status, COUNT(*) as total')
            ->groupBy('waktu', 'status')
            ->get();

        $sholatKelas = DB::table('absensi_sholat')
            ->whereIn('kelas_id', $idKelas)
            ->where('tanggal', $tgl)
            ->selectRaw('waktu, COUNT(DISTINCT kelas_id) as total')
            ->groupBy('waktu')
            ->pluck('total', 'waktu');

        $kelasSholat = DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->where('s.is_active', true)
            ->where('s.agama', 'Islam')
            ->whereIn('ks.kelas_id', $idKelas)
            ->distinct()
            ->count('ks.kelas_id');

        $ambilSholat = function (string $waktu) use ($sholatRaw, $sholatKelas, $persen) {
            $g = $sholatRaw->where('waktu', $waktu);
            $hadir = (int) $g->where('status', 'hadir')->sum('total');
            $tidak = (int) $g->where('status', 'tidak_hadir')->sum('total');
            $halangan = (int) $g->where('status', 'berhalangan')->sum('total');

            return [
                'hadir' => $hadir,
                'tidak_hadir' => $tidak,
                'berhalangan' => $halangan,
                'persen' => $persen($hadir, $hadir + $tidak),
                'kelas' => (int) $sholatKelas->get($waktu, 0),
            ];
        };

        // ---------- Jurnal hari ini ----------
        $jadwalHari = DB::table('jadwal as jd')
            ->join('kelas as k', 'k.id', '=', 'jd.kelas_id')
            ->leftJoin('mapel as m', 'm.id', '=', 'jd.mapel_id')
            ->where('k.tahun_ajaran_id', $ta->id)
            ->where('jd.hari', $hariData->isoWeekday())
            ->get(['jd.id', 'jd.guru_id', 'jd.jam_ke_mulai', 'jd.jam_ke_selesai', 'jd.jam_mulai', 'jd.jam_selesai', 'k.nama as kelas', 'm.nama as mapel']);

        $sudahJurnal = DB::table('jurnal')
            ->whereIn('jadwal_id', $jadwalHari->pluck('id'))
            ->where('tanggal', $tgl)
            ->pluck('jadwal_id')
            ->flip();

        $namaGuru = User::whereIn('id', $jadwalHari->pluck('guru_id')->unique())->pluck('name', 'id');

        $jamSekarang = $sekarang->format('H:i');
        $sudahSelesai = fn ($j) => ! $adalahHariIni || substr((string) $j->jam_selesai, 0, 5) <= $jamSekarang;

        $sesiTerisi = $jadwalHari->filter(fn ($j) => $sudahJurnal->has($j->id));
        $sesiTerlewat = $jadwalHari->filter(fn ($j) => ! $sudahJurnal->has($j->id) && $sudahSelesai($j));

        // ---------- Kepatuhan jurnal bulan ini ----------
        $kepatuhan = KepatuhanJurnal::hitung($hariIni->copy()->startOfMonth(), $hariIni->copy()->endOfMonth());

        // ---------- Pelanggaran ----------
        $awalBulan = $hariIni->copy()->startOfMonth();
        $akhirBulan = $hariIni->copy()->endOfMonth();
        $awalLalu = $awalBulan->copy()->subMonth();
        $akhirLalu = $awalLalu->copy()->endOfMonth();

        $hitungPelanggaran = fn (Carbon $a, Carbon $b) => DB::table('pelanggaran_siswa')
            ->whereBetween('tanggal', [$a->toDateString(), $b->toDateString()])
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(poin), 0) as poin')
            ->first();

        $bulanIni = $hitungPelanggaran($awalBulan, $akhirBulan);
        $bulanLalu = $hitungPelanggaran($awalLalu, $akhirLalu);

        $mulaiMinggu = $hariIni->copy()->startOfWeek()->subWeeks(7);

        $pelMinggu = DB::table('pelanggaran_siswa')
            ->whereBetween('tanggal', [$mulaiMinggu->toDateString(), $hariIni->toDateString()])
            ->get(['tanggal'])
            ->groupBy(fn ($r) => Carbon::parse($r->tanggal)->startOfWeek()->toDateString());

        $mingguPelanggaran = collect(range(0, 7))->map(function (int $i) use ($mulaiMinggu, $pelMinggu) {
            $senin = $mulaiMinggu->copy()->addWeeks($i);

            return [
                'label' => $senin->copy()->locale('id')->translatedFormat('j M'),
                'jumlah' => $pelMinggu->get($senin->toDateString(), collect())->count(),
            ];
        })->all();

        $jenisTeratas = DB::table('pelanggaran_siswa as p')
            ->join('jenis_pelanggaran as j', 'j.id', '=', 'p.jenis_pelanggaran_id')
            ->whereBetween('p.tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->groupBy('j.nama')
            ->selectRaw('j.nama as nama, COUNT(*) as jumlah, SUM(p.poin) as poin')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['nama' => $r->nama, 'jumlah' => (int) $r->jumlah, 'poin' => (int) $r->poin])
            ->all();

        $pelJurusan = DB::table('pelanggaran_siswa as p')
            ->join('kelas_siswa as ks', 'ks.siswa_id', '=', 'p.siswa_id')
            ->join('kelas as k', 'k.id', '=', 'ks.kelas_id')
            ->leftJoin('jurusan as j', 'j.id', '=', 'k.jurusan_id')
            ->where('k.tahun_ajaran_id', $ta->id)
            ->whereBetween('p.tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->groupByRaw("COALESCE(j.nama, 'Tanpa jurusan')")
            ->selectRaw("COALESCE(j.nama, 'Tanpa jurusan') as jurusan, COUNT(*) as jumlah")
            ->pluck('jumlah', 'jurusan');

        // ---------- Poin aktif dan SP ----------
        $aturan = DB::table('aturan_sp')->orderBy('level')->get(['level', 'nama', 'batas_poin']);
        $rentangTa = [$ta->tanggal_mulai->toDateString(), $ta->tanggal_selesai->toDateString()];

        $pelSiswa = DB::table('pelanggaran_siswa')
            ->whereBetween('tanggal', $rentangTa)
            ->groupBy('siswa_id')
            ->selectRaw('siswa_id, SUM(poin) as total')
            ->pluck('total', 'siswa_id');

        $remSiswa = DB::table('remisi_siswa')
            ->whereBetween('tanggal', $rentangTa)
            ->groupBy('siswa_id')
            ->selectRaw('siswa_id, SUM(poin) as total')
            ->pluck('total', 'siswa_id');

        // siswa_id => poin aktif (hanya yang lebih dari 0)
        $aktifSiswa = $pelSiswa
            ->map(fn ($total, $id) => max(0, (int) $total - (int) $remSiswa->get($id, 0)))
            ->filter(fn (int $v) => $v > 0);

        // Mendekati ambang berikutnya (sisa 10 poin atau kurang)
        $mendekati = $aktifSiswa
            ->map(function (int $aktif) use ($aturan) {
                $berikut = $aturan->first(fn ($a) => (int) $a->batas_poin > $aktif);

                return [
                    'aktif' => $aktif,
                    'berikut' => $berikut ? ['nama' => $berikut->nama, 'batas' => (int) $berikut->batas_poin] : null,
                ];
            })
            ->filter(fn (array $x) => $x['berikut'] !== null && ($x['berikut']['batas'] - $x['aktif']) <= 10);

        $urutMendekati = $mendekati->sortBy(fn (array $x) => $x['berikut']['batas'] - $x['aktif'])->take(6);
        $urutTertinggi = $aktifSiswa->sortDesc()->take(6);

        $spMenunggu = DB::table('sp_siswa as s')
            ->join('aturan_sp as a', 'a.id', '=', 's.aturan_sp_id')
            ->where('s.status', 'menunggu')
            ->orderByDesc('s.created_at')
            ->get(['s.siswa_id', 's.total_poin_saat_terbit', 's.created_at', 'a.nama as aturan']);

        $idDibutuhkan = $urutMendekati->keys()
            ->merge($urutTertinggi->keys())
            ->merge($spMenunggu->take(6)->pluck('siswa_id'))
            ->unique()
            ->values();

        $infoSiswa = DB::table('siswa as s')
            ->join('kelas_siswa as ks', 'ks.siswa_id', '=', 's.id')
            ->join('kelas as k', 'k.id', '=', 'ks.kelas_id')
            ->where('k.tahun_ajaran_id', $ta->id)
            ->whereIn('s.id', $idDibutuhkan)
            ->get(['s.id', 's.nama', 'k.nama as kelas'])
            ->keyBy('id');

        // ---------- Per jurusan ----------
        $jurusan = $perKelas->groupBy('jurusan')->map(function ($g, $nama) use ($persen, $pelJurusan) {
            $hadir = (int) $g->sum('hadir');
            $tercatat = (int) $g->sum('tercatat');

            return [
                'nama' => $nama,
                'kelas' => $g->count(),
                'siswa' => (int) $g->sum('siswa'),
                'hadir' => $hadir,
                'tercatat' => $tercatat,
                'persen' => $persen($hadir, $tercatat),
                'pelanggaran_bulan' => (int) $pelJurusan->get($nama, 0),
            ];
        })->sortBy('nama')->values()->all();

        // ---------- Ringkasan ----------
        $kelasBelum = $perKelas->where('tercatat', 0)->map(fn (array $k) => [
            'id' => $k['id'], 'nama' => $k['nama'], 'jurusan' => $k['jurusan'], 'siswa' => $k['siswa'],
        ])->values();

        $jurnalTerisi = $sesiTerisi->count();
        $jurnalTerlewat = $sesiTerlewat->count();

        // ---------- Peringatan otomatis ----------
        $peringatan = [];

        if ($kelasBelum->isNotEmpty()) {
            $peringatan[] = [
                'tingkat' => ($adalahHariIni && $sekarang->hour >= 9) ? 'danger' : 'info',
                'judul' => $kelasBelum->count() . ' kelas belum diabsen',
                'detail' => $kelasBelum->take(4)->pluck('nama')->implode(', ') . ($kelasBelum->count() > 4 ? ', dan lainnya' : ''),
                'tautan' => null,
            ];
        }

        if ($jurnalTerlewat > 0) {
            $peringatan[] = [
                'tingkat' => 'danger',
                'judul' => $jurnalTerlewat . ' sesi belum dijurnal',
                'detail' => 'Sesi yang sudah selesai tetapi jurnalnya masih kosong.',
                'tautan' => '/pimpinan/jurnal',
            ];
        }

        if ($persenHadir !== null && $persenHadir < 90) {
            $peringatan[] = [
                'tingkat' => $persenHadir < 80 ? 'danger' : 'warn',
                'judul' => 'Kehadiran siswa ' . $persenHadir . '%',
                'detail' => 'Di bawah 90% pada ' . $hariData->locale('id')->translatedFormat('l, j F') . '.',
                'tautan' => null,
            ];
        }

        if ($kepatuhan['ringkasan']['kurang'] > 0) {
            $peringatan[] = [
                'tingkat' => 'warn',
                'judul' => $kepatuhan['ringkasan']['kurang'] . ' guru berkepatuhan jurnal kurang',
                'detail' => 'Di bawah 75% pada bulan ini.',
                'tautan' => '/pimpinan/jurnal',
            ];
        }

        if ($spMenunggu->count() > 0) {
            $peringatan[] = [
                'tingkat' => 'warn',
                'judul' => $spMenunggu->count() . ' usulan SP menunggu persetujuan',
                'detail' => 'Menunggu keputusan kesiswaan.',
                'tautan' => null,
            ];
        }

        if ($mendekati->count() > 0) {
            $peringatan[] = [
                'tingkat' => 'info',
                'judul' => $mendekati->count() . ' siswa mendekati batas SP',
                'detail' => 'Sisa 10 poin atau kurang dari ambang berikutnya.',
                'tautan' => null,
            ];
        }

        if ($peringatan === []) {
            $peringatan[] = ['tingkat' => 'ok', 'judul' => 'Semua terkendali', 'detail' => 'Tidak ada hal yang perlu ditindaklanjuti saat ini.', 'tautan' => null];
        }

        return [
            'diperbarui' => $sekarang->toIso8601String(),
            'hari' => [
                'tanggal' => $tgl,
                'label' => $hariData->copy()->locale('id')->translatedFormat('l, j F Y'),
                'hari_ini' => $adalahHariIni,
                'libur' => $libur,
            ],
            'ringkasan' => [
                'siswa' => $totalSiswa,
                'kelas' => $kelasAktif->count(),
                'guru_mengajar' => $jadwalHari->pluck('guru_id')->unique()->count(),
                'pelanggaran_bulan' => [
                    'jumlah' => (int) $bulanIni->jumlah,
                    'poin' => (int) $bulanIni->poin,
                    'jumlah_lalu' => (int) $bulanLalu->jumlah,
                    'poin_lalu' => (int) $bulanLalu->poin,
                ],
                'sp_menunggu' => $spMenunggu->count(),
                'mendekati_sp' => $mendekati->count(),
            ],
            'absensi' => [
                'total_siswa' => $totalSiswa,
                'tercatat' => $tercatatTotal,
                'hadir' => $hadirTotal,
                'sakit' => (int) $perKelas->sum('sakit'),
                'izin' => (int) $perKelas->sum('izin'),
                'alpa' => (int) $perKelas->sum('alpa'),
                'belum' => max(0, $totalSiswa - $tercatatTotal),
                'persen' => $persenHadir,
                'kelas_total' => $kelasAktif->count(),
                'kelas_diabsen' => $perKelas->where('tercatat', '>', 0)->count(),
            ],
            'tren_absensi' => $tren->all(),
            'peta_panas' => [
                'tanggal' => $tanggalPeta->map(fn ($t) => Carbon::parse($t)->format('j/n'))->all(),
                'kelas' => $petaKelas->all(),
            ],
            'kelas_belum' => $kelasBelum->all(),
            'kelas_terendah' => $perKelas
                ->filter(fn (array $k) => $k['tercatat'] > 0)
                ->sortBy('persen')
                ->take(6)
                ->map(fn (array $k) => [
                    'id' => $k['id'], 'nama' => $k['nama'], 'jurusan' => $k['jurusan'], 'siswa' => $k['siswa'],
                    'persen' => $k['persen'], 'hadir' => $k['hadir'], 'tercatat' => $k['tercatat'],
                ])
                ->values()
                ->all(),
            'sholat' => [
                'dhuhur' => $ambilSholat('dhuhur'),
                'ashar' => $ambilSholat('ashar'),
                'kelas_total' => $kelasSholat,
            ],
            'jurnal_hari' => [
                'total' => $jadwalHari->count(),
                'terisi' => $jurnalTerisi,
                'terlewat' => $jurnalTerlewat,
                'berjalan' => $jadwalHari->count() - $jurnalTerisi - $jurnalTerlewat,
                'persen' => $persen($jurnalTerisi, $jurnalTerisi + $jurnalTerlewat),
                'daftar_terlewat' => $sesiTerlewat->sortBy('jam_ke_mulai')->take(10)->map(fn ($j) => [
                    'guru' => $namaGuru->get($j->guru_id) ?? '-',
                    'kelas' => $j->kelas,
                    'mapel' => $j->mapel,
                    'jam_ke_mulai' => (int) $j->jam_ke_mulai,
                    'jam_ke_selesai' => (int) $j->jam_ke_selesai,
                    'jam_mulai' => substr((string) $j->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
                ])->values()->all(),
            ],
            'kepatuhan' => [
                'ringkasan' => $kepatuhan['ringkasan'],
                'mingguan' => $kepatuhan['mingguan'],
                'terendah' => collect($kepatuhan['guru'])->filter(fn (array $g) => $g['seharusnya'] > 0)->take(5)->values()->all(),
            ],
            'pelanggaran' => [
                'mingguan' => $mingguPelanggaran,
                'jenis_teratas' => $jenisTeratas,
            ],
            'sp' => [
                'menunggu' => $spMenunggu->take(6)->map(fn ($r) => [
                    'siswa' => $infoSiswa->get($r->siswa_id)?->nama ?? '-',
                    'kelas' => $infoSiswa->get($r->siswa_id)?->kelas ?? '-',
                    'aturan' => $r->aturan,
                    'poin' => (int) $r->total_poin_saat_terbit,
                    'tanggal' => substr((string) $r->created_at, 0, 10),
                ])->values()->all(),
                'mendekati' => $urutMendekati->map(fn (array $x, $id) => [
                    'nama' => $infoSiswa->get($id)?->nama ?? '-',
                    'kelas' => $infoSiswa->get($id)?->kelas ?? '-',
                    'poin_aktif' => $x['aktif'],
                    'sisa' => $x['berikut']['batas'] - $x['aktif'],
                    'menuju' => $x['berikut']['nama'],
                ])->values()->all(),
                'poin_tertinggi' => $urutTertinggi->map(fn (int $aktif, $id) => [
                    'nama' => $infoSiswa->get($id)?->nama ?? '-',
                    'kelas' => $infoSiswa->get($id)?->kelas ?? '-',
                    'poin_aktif' => $aktif,
                ])->values()->all(),
            ],
            'jurusan' => $jurusan,
            'peringatan' => $peringatan,
        ];
    }
}