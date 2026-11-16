<?php

namespace App\Filament\Widgets;

use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\PelanggaranSiswa;
use App\Models\SpSiswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikHariIni extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan Hari Ini';


    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->punyaPeran(...User::PERAN_PEMANTAU);
    }

    protected function getStats(): array
    {
        $ta = TahunAjaran::aktif();

        if (! $ta) {
            return [
                Stat::make('Tahun ajaran', 'Belum diatur')
                    ->description('Aktifkan satu tahun ajaran di menu Master Data')
                    ->color('danger'),
            ];
        }

        $hari = now()->toDateString();

        // Absensi hari ini, dikelompokkan per status
        $absen = Absensi::where('tanggal', $hari)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $hadir = (int) ($absen['hadir'] ?? 0);
        $sakit = (int) ($absen['sakit'] ?? 0);
        $izin = (int) ($absen['izin'] ?? 0);
        $alpa = (int) ($absen['alpa'] ?? 0);
        $tercatat = $hadir + $sakit + $izin + $alpa;
        $persen = $tercatat > 0 ? (int) round($hadir / $tercatat * 100) : null;

        // Kelas (yang punya siswa) yang belum diabsen hari ini
        $kelasTotal = Kelas::where('tahun_ajaran_id', $ta->id)->has('siswa')->count();
        $kelasBelum = Kelas::where('tahun_ajaran_id', $ta->id)
            ->has('siswa')
            ->whereNotIn('id', Absensi::where('tanggal', $hari)->select('kelas_id'))
            ->count();

        // Jurnal: terisi vs jadwal hari ini
        $jadwalHariIni = Jadwal::where('tahun_ajaran_id', $ta->id)
            ->where('hari', now()->dayOfWeekIso)
            ->count();
        $jurnalTerisi = Jurnal::where('tanggal', $hari)->count();

        $pelanggaranHariIni = PelanggaranSiswa::where('tahun_ajaran_id', $ta->id)
            ->where('tanggal', $hari)
            ->count();

        $spMenunggu = SpSiswa::where('tahun_ajaran_id', $ta->id)
            ->where('status', 'menunggu')
            ->count();

        return [
            Stat::make('Kehadiran siswa', $persen === null ? '-' : $persen . '%')
                ->description("{$hadir} dari {$tercatat} siswa tercatat")
                ->icon('heroicon-o-user-group')
                ->color($persen === null ? 'gray' : ($persen >= 90 ? 'success' : ($persen >= 75 ? 'warning' : 'danger'))),

            Stat::make('Tidak hadir', $sakit + $izin + $alpa)
                ->description("Sakit {$sakit} • Izin {$izin} • Alpa {$alpa}")
                ->icon('heroicon-o-user-minus')
                ->color($alpa > 0 ? 'danger' : 'gray'),

            Stat::make('Kelas belum diabsen', $kelasBelum)
                ->description("dari {$kelasTotal} kelas")
                ->icon('heroicon-o-clipboard-document-check')
                ->color($kelasBelum > 0 ? 'warning' : 'success'),

            Stat::make('Jurnal terisi', "{$jurnalTerisi} / {$jadwalHariIni}")
                ->description('jurnal dari jadwal hari ini')
                ->icon('heroicon-o-book-open')
                ->color($jadwalHariIni > 0 && $jurnalTerisi < $jadwalHariIni ? 'warning' : 'success'),

            Stat::make('Pelanggaran hari ini', $pelanggaranHariIni)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($pelanggaranHariIni > 0 ? 'warning' : 'gray'),

            Stat::make('SP menunggu persetujuan', $spMenunggu)
                ->description('perlu ditinjau kesiswaan')
                ->icon('heroicon-o-document-text')
                ->color($spMenunggu > 0 ? 'danger' : 'gray'),
        ];
    }
}