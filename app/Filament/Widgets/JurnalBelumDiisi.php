<?php

namespace App\Filament\Widgets;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class JurnalBelumDiisi extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->punyaPeran(...User::PERAN_PEMANTAU);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Jurnal Belum Diisi Hari Ini')
            ->description('Jadwal hari ini yang jam pelajarannya sudah lewat tapi belum ada jurnalnya.')
            ->query(function (): Builder {
                $ta = TahunAjaran::aktif();

                if (! $ta) {
                    return Jadwal::query()->whereRaw('1 = 0');
                }

                return Jadwal::query()
                    ->where('tahun_ajaran_id', $ta->id)
                    ->where('hari', now()->dayOfWeekIso)
                    ->where('jam_selesai', '<', now()->format('H:i:s'))
                    ->whereNotIn(
                        'id',
                        Jurnal::where('tanggal', now()->toDateString())->select('jadwal_id')
                    )
                    ->with(['guru:id,name', 'kelas:id,nama', 'mapel:id,nama', 'ruang:id,nama']);
            })
            ->columns([
                TextColumn::make('guru.name')
                    ->label('Guru')
                    ->searchable(),

                TextColumn::make('kelas.nama')
                    ->label('Kelas'),

                TextColumn::make('mapel.nama')
                    ->label('Mapel'),

                TextColumn::make('jam_mulai')
                    ->label('Waktu')
                    ->state(fn (Jadwal $record): string => substr($record->jam_mulai, 0, 5) . ' - ' . substr($record->jam_selesai, 0, 5)),

                TextColumn::make('ruang.nama')
                    ->label('Ruang')
                    ->placeholder('-'),
            ])
            ->defaultSort('jam_mulai')
            ->paginated([5, 10])
            ->emptyStateHeading('Semua jurnal yang sudah lewat sudah terisi');
    }
}