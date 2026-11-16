<?php

namespace App\Filament\Widgets;

use App\Models\Absensi;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TrenKehadiran extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Tren Kehadiran (14 hari terakhir)';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->punyaPeran(...User::PERAN_PEMANTAU);
    }

    protected function getData(): array
    {
        $mulai = now()->subDays(13)->startOfDay();

        $rekap = Absensi::whereBetween('tanggal', [$mulai->toDateString(), now()->toDateString()])
            ->selectRaw("tanggal, SUM(status = 'hadir') as hadir, COUNT(*) as total")
            ->groupBy('tanggal')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->tanggal)->toDateString());

        $labels = [];
        $data = [];

        for ($i = 0; $i < 14; $i++) {
            $tgl = $mulai->copy()->addDays($i);

            if ($tgl->isWeekend()) {
                continue; // Sabtu dan Minggu tidak ditampilkan
            }

            $row = $rekap->get($tgl->toDateString());

            $labels[] = $tgl->locale('id')->translatedFormat('D d M');
            $data[] = $row && $row->total > 0
                ? round($row->hadir / $row->total * 100, 1)
                : null; // tidak ada data = garis terputus, bukan 0%
        }

        return [
            'datasets' => [[
                'label' => 'Kehadiran (%)',
                'data' => $data,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                'tension' => 0.3,
                'fill' => true,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['min' => 0, 'max' => 100]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}