<?php

namespace App\Filament\Resources\JenisPelanggarans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class JenisPelanggaransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Pelanggaran')
                    ->searchable(),

                TextColumn::make('kategori')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ringan' => 'success',
                        'sedang' => 'warning',
                        'berat' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),

                TextColumn::make('poin')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => "+{$state} poin")
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->options(['ringan' => 'Ringan', 'sedang' => 'Sedang', 'berat' => 'Berat']),
            ])
            ->defaultSort('poin')
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
