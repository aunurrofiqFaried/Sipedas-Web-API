<?php

namespace App\Filament\Resources\KalenderAkademiks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\KalenderAkademik;
use Filament\Tables\Filters\SelectFilter;

class KalenderAkademiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')
                    ->label('Acara')
                    ->searchable(),

                TextColumn::make('jenis')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'libur' ? 'danger' : 'info')
                    ->formatStateUsing(fn (string $state): string => $state === 'libur' ? 'Libur' : 'Peringatan'),

                TextColumn::make('tanggal_mulai')
                    ->label('Tanggal')
                    ->sortable()
                    ->state(function (KalenderAkademik $r): string {
                        $a = $r->tanggal_mulai->copy()->locale('id');
                        $b = $r->tanggal_selesai->copy()->locale('id');

                        return $a->isSameDay($b)
                            ? $a->translatedFormat('d M Y')
                            : $a->translatedFormat('d M Y') . ' – ' . $b->translatedFormat('d M Y');
                    }),

                TextColumn::make('keterangan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(['libur' => 'Libur', 'peringatan' => 'Peringatan']),
            ])
            ->defaultSort('tanggal_mulai')
            ->paginated([25, 50])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
