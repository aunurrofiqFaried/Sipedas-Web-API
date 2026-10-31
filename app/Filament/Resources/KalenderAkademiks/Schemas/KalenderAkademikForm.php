<?php

namespace App\Filament\Resources\KalenderAkademiks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use App\Models\KalenderAkademik;

class KalenderAkademikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')
                ->label('Nama hari / kegiatan')
                ->placeholder('Contoh: Hari Kemerdekaan RI')
                ->required()
                ->maxLength(150),

            Select::make('jenis')
                ->options(KalenderAkademik::JENIS)
                ->default('libur')
                ->required(),

            DatePicker::make('tanggal_mulai')
                ->label('Tanggal mulai')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y'),

            DatePicker::make('tanggal_selesai')
                ->label('Tanggal selesai')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->afterOrEqual('tanggal_mulai')
                ->helperText('Untuk satu hari saja, isi sama dengan tanggal mulai.'),

            Textarea::make('keterangan')
                ->label('Keterangan (opsional)')
                ->rows(3)
                ->maxLength(1000),
        ]);
    }
}
