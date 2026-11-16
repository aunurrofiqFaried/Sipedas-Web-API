<?php

namespace App\Filament\Widgets;

use App\Models\PelanggaranSiswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class PelanggaranPerJenis extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Pelanggaran per Jenis (tahun ajaran aktif)';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->punyaPeran(...User::PERAN_PEMANTAU);
    }

    protected function getData(): array
    {
        $ta = TahunAjaran::aktif();

        if (! $ta) {
            return ['datasets' => [['data' => []]], 'labels' => []];
        }

        // 8 jenis pelanggaran dengan kasus terbanyak
        $rekap = PelanggaranSiswa::where('tahun_ajaran_id', $ta->id)
            ->selectRaw('jenis_pelanggaran_id, COUNT(*) as total')
            ->groupBy('jenis_pelanggaran_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('jenis:id,nama')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Jumlah kasus',
                'data' => $rekap->pluck('total')->map(fn ($v) => (int) $v)->all(),
                'backgroundColor' => '#f59e0b',
            ]],
            'labels' => $rekap->map(fn ($r) => $r->jenis?->nama ?? '-')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // batang mendatar supaya nama pelanggaran terbaca
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}