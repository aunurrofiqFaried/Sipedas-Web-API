<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama lengkap')
                ->required()
                ->maxLength(255),

            TextInput::make('username')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('nip')
                ->label('NIP')
                ->maxLength(30),

            TextInput::make('email')
                ->email()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Select::make('role')
                ->label('Peran utama')
                ->options(User::ROLE_LABELS)
                ->default(User::ROLE_GURU)
                ->required()
                ->live()
                ->helperText('Untuk orang yang mengajar, pilih Guru sebagai peran utama.'),

            Select::make('peran_tambahan')
                ->label('Peran tambahan')
                ->multiple()
                ->options(fn (Get $get): array => collect(User::ROLE_LABELS)
                    ->except($get('role'))
                    ->all())
                ->helperText('Kosongkan bila hanya punya satu peran. Contoh: guru yang juga admin.')
                ->dehydrateStateUsing(fn (?array $state): ?array => filled($state) ? array_values($state) : null),

            TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Saat mengubah data, kosongkan jika password tidak diganti.'),

            Toggle::make('is_active')
                ->label('Akun aktif')
                ->default(true),
        ]);
    }
}