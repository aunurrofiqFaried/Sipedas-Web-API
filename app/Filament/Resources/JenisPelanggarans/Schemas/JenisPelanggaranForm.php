<?php

namespace App\Filament\Resources\JenisPelanggarans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class JenisPelanggaranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')
                ->label('Jenis pelanggaran')
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true),

            Select::make('kategori')
                ->options([
                    'ringan' => 'Ringan',
                    'sedang' => 'Sedang',
                    'berat' => 'Berat',
                ])
                ->required()
                ->helperText('Dipakai untuk mengelompokkan dan mewarnai pilihan di aplikasi.'),

            TextInput::make('poin')
                ->label('Poin')
                ->numeric()
                ->minValue(1)
                ->maxValue(200)
                ->required()
                ->helperText('Perubahan poin hanya berlaku untuk pelanggaran yang dicatat sesudahnya. Riwayat lama tidak berubah.'),

            Toggle::make('is_active')
                ->label('Aktif (bisa dipilih di aplikasi)')
                ->default(true),
        ]);
    }
}
