<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Imports\UserImporter;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->searchable(),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable(),

                TextColumn::make('role')
                    ->label('Peran utama')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => User::ROLE_LABELS[$state] ?? ($state ?: 'Belum diisi')),

                TextColumn::make('peran_tambahan')
                    ->label('Peran tambahan')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => User::ROLE_LABELS[$state] ?? (string) $state)
                    ->placeholder('-'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                // Mencakup peran utama maupun peran tambahan
                SelectFilter::make('peran')
                    ->label('Peran')
                    ->options(User::ROLE_LABELS)
                    ->query(function ($query, array $data) {
                        $nilai = $data['value'] ?? null;

                        if (blank($nilai)) {
                            return $query;
                        }

                        return $query->where(fn ($q) => $q
                            ->where('role', $nilai)
                            ->orWhereJsonContains('peran_tambahan', $nilai));
                    }),
            ])
            ->headerActions([
                ImportAction::make()
                    ->label('Import guru dari CSV')
                    ->importer(UserImporter::class),
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