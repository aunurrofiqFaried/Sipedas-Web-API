<?php

namespace App\Filament\Resources\JenisRemisis\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class JenisRemisiForm
{
    public static function configure(Schema $schema): Schema
    {
    return $schema->components([
        TextInput::make('nama')
            ->label('Kegiatan atau bentuk ketertiban')
            ->required()
            ->maxLength(150),

        TextInput::make('poin')
            ->label('Poin yang dikurangi')
            ->numeric()
            ->minValue(1)
            ->maxValue(100)
            ->required(),

        Textarea::make('keterangan')
            ->label('Syarat atau penjelasan (opsional)')
            ->rows(3)
            ->maxLength(1000),

        Toggle::make('is_aktif')
            ->label('Aktif (bisa dipilih di aplikasi)')
            ->default(true),
    ]);
    }
}
