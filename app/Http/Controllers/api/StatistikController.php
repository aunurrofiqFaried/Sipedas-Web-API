<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\PelanggaranSiswa;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatistikController extends Controller
{
    // GET /api/statistik/guru
    public function guru(Request $request): JsonResponse
    {
        $user = $request->user();
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $hariIni = now();
        $awalBulan = $hariIni->copy()->startOfMonth();

        $jadwal = Jadwal::where('tahun_ajaran_id', $ta->id)
            ->where('guru_id', $user->id)
            ->get(['id', 'kelas_id', 'mapel_id', 'hari', 'jam_ke_mulai', 'jam_ke_selesai']);

        $idKelas = $jadwal->pluck('kelas_id')->unique()->values();

        // ===== Mengajar =====
        $jamPerHari = $jadwal->groupBy('hari')
            ->map(fn ($g) => $g->sum(fn ($j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1));

        $hariTerpadat = $jamPerHari->sortDesc()->keys()->first();

        $jumlahSiswa = DB::table('kelas_siswa')
            ->join('siswa', 'siswa.id', '=', 'kelas_siswa.siswa_id')
            ->where('kelas_siswa.tahun_ajaran_id', $ta->id)
            ->whereIn('kelas_siswa.kelas_id', $idKelas)
            ->where('siswa.is_active', true)
            ->distinct()
            ->count('kelas_siswa.siswa_id');

        // ===== Kepatuhan jurnal bulan ini (sampai kemarin) =====
        $sampai = $hariIni->copy()->subDay();

        $libur = KalenderAkademik::where('jenis', 'libur')
            ->where('tanggal_mulai', '<=', $hariIni->toDateString())
            ->where('tanggal_selesai', '>=', $awalBulan->toDateString())
            ->get();

        $sesiPerHari = $jadwal->countBy('hari');
        $seharusnya = 0;

        for ($d = $awalBulan->copy(); $d->lte($sampai); $d->addDay()) {
            if ($d->isWeekend() || $d->lt($ta->tanggal_mulai) || $d->gt($ta->tanggal_selesai)) {
                continue;
            }

            $adaLibur = $libur->contains(fn ($e) => $d->between($e->tanggal_mulai, $e->tanggal_selesai));

            if (! $adaLibur) {
                $seharusnya += (int) ($sesiPerHari[$d->dayOfWeekIso] ?? 0);
            }
        }

        $terisi = Jurnal::where('guru_id', $user->id)
            ->whereBetween('tanggal', [$awalBulan->toDateString(), $sampai->toDateString()])
            ->count();

        // ===== Kelas yang diwalikan =====
        $kelasWali = Kelas::where('tahun_ajaran_id', $ta->id)
            ->where('wali_kelas_id', $user->id)
            ->orderBy('nama')
            ->get()
            ->map(function (Kelas $k) use ($ta, $awalBulan, $hariIni) {
                $rentang = [$awalBulan->toDateString(), $hariIni->toDateString()];

                $rekap = Absensi::where('kelas_id', $k->id)
                    ->whereBetween('tanggal', $rentang)
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $hadir = (int) ($rekap['hadir'] ?? 0);
                $sakit = (int) ($rekap['sakit'] ?? 0);
                $izin = (int) ($rekap['izin'] ?? 0);
                $alpa = (int) ($rekap['alpa'] ?? 0);
                $total = $hadir + $sakit + $izin + $alpa;

                return [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'jumlah_siswa' => DB::table('kelas_siswa')
                        ->where('kelas_id', $k->id)
                        ->where('tahun_ajaran_id', $ta->id)
                        ->count(),
                    'kehadiran_persen' => $total > 0 ? (int) round($hadir / $total * 100) : null,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpa' => $alpa,
                    'hari_tercatat' => Absensi::where('kelas_id', $k->id)
                        ->whereBetween('tanggal', $rentang)
                        ->distinct()
                        ->count('tanggal'),
                ];
            })
            ->values();

        return response()->json([
            'mengajar' => [
                'jumlah_kelas' => $idKelas->count(),
                'jumlah_mapel' => $jadwal->pluck('mapel_id')->unique()->count(),
                'jam_per_minggu' => (int) $jamPerHari->sum(),
                'jumlah_siswa' => $jumlahSiswa,
                'hari_terpadat' => $hariTerpadat
                    ? ['hari' => (int) $hariTerpadat, 'jam' => (int) $jamPerHari[$hariTerpadat]]
                    : null,
            ],
            'jurnal_bulan_ini' => [
                'terisi' => $terisi,
                'seharusnya' => $seharusnya,
                'persen' => $seharusnya > 0 ? min(100, (int) round($terisi / $seharusnya * 100)) : null,
            ],
            'kelas_wali' => $kelasWali,
            'pelanggaran_dicatat' => PelanggaranSiswa::where('dicatat_oleh', $user->id)
                ->whereBetween('tanggal', [$awalBulan->toDateString(), $hariIni->toDateString()])
                ->count(),
        ]);
    }
}