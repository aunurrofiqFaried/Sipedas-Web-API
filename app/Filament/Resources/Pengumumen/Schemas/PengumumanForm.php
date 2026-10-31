<?php

namespace App\Filament\Resources\Pengumumen\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Models\Pengumuman;
use Filament\Forms\Components\Hidden;


class PengumumanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')
                ->required()
                ->maxLength(150)
                ->placeholder('Contoh: Kabar duka'),

            Select::make('tingkat')
                ->options(Pengumuman::TINGKAT)
                ->default('biasa')
                ->required()
                ->helperText('Penting ditandai merah di aplikasi.'),

            Textarea::make('isi')
                ->required()
                ->rows(6)
                ->maxLength(5000),

            DatePicker::make('berlaku_sampai')
                ->label('Berlaku sampai (opsional)')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->helperText('Kosongkan agar tampil terus selama aktif.'),

            Toggle::make('is_aktif')
                ->label('Tampilkan di aplikasi')
                ->default(true)
                ->helperText('Matikan untuk menarik pengumuman tanpa menghapusnya.'),

            Hidden::make('dibuat_oleh')
                ->default(fn () => auth()->id()),
        ]);
    }
}
