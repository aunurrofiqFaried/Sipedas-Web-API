<?php

namespace App\Filament\Resources\Siswas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Siswa;

class SiswaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data siswa')
                ->schema([
                    TextInput::make('nis')
                        ->label('NIS')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true),

                    TextInput::make('nisn')
                        ->label('NISN')
                        ->maxLength(20)
                        ->unique(ignoreRecord: true),

                    TextInput::make('nama')
                        ->label('Nama lengkap')
                        ->required()
                        ->maxLength(255),

                    Select::make('jenis_kelamin')
                        ->label('Jenis kelamin')
                        ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                        ->required(),

                    Select::make('agama')
                        ->options(array_combine(Siswa::AGAMA, Siswa::AGAMA))
                        ->default('Islam')
                        ->required()
                        ->helperText('Siswa non-muslim tidak ikut absensi sholat.'),
                ])
                ->columns(2),

            Section::make('Status siswa')
                ->description('Siswa yang lulus, pindah, atau keluar tidak muncul lagi di absensi, tetapi riwayatnya tetap tersimpan.')
                ->schema([
                    Select::make('status')
                        ->options(Siswa::STATUS)
                        ->default(Siswa::STATUS_AKTIF)
                        ->required()
                        ->live()
                        ->native(false),

                    DatePicker::make('tanggal_keluar')
                        ->label(fn (Get $get) => $get('status') === Siswa::STATUS_LULUS ? 'Tanggal lulus' : 'Tanggal keluar')
                        ->default(now())
                        ->required(fn (Get $get) => $get('status') !== Siswa::STATUS_AKTIF)
                        ->visible(fn (Get $get) => $get('status') !== Siswa::STATUS_AKTIF),

                    TextInput::make('keterangan_keluar')
                        ->label('Keterangan')
                        ->placeholder('Contoh: pindah ke SMK Negeri 1 Lamongan')
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('status') !== Siswa::STATUS_AKTIF)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Data orang tua / wali')
                ->description('Dipakai untuk tombol WhatsApp saat siswa tidak hadir atau dihubungi BK.')
                ->schema([
                    TextInput::make('nama_wali')
                        ->label('Nama wali')
                        ->maxLength(100),

                    Select::make('hubungan_wali')
                        ->label('Hubungan')
                        ->options(['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali lain']),

                    TextInput::make('no_hp_wali')
                        ->label('No. HP / WhatsApp')
                        ->tel()
                        ->maxLength(20)
                        ->regex('/^[0-9+\-\s]+$/')
                        ->placeholder('081234567890')
                        ->helperText('Format bebas (0812..., +62812...). Sistem merapikannya otomatis.'),
                ])
                ->columns(2),
        ]);
    }
}