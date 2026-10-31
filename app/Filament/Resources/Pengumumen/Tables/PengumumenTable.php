<?php

namespace App\Filament\Resources\Pengumumen\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PengumumenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('tingkat')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'penting' ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('berlaku_sampai')
                    ->label('Berlaku sampai')
                    ->date('d M Y')
                    ->placeholder('Tanpa batas'),

                IconColumn::make('is_aktif')
                    ->label('Tampil')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
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
