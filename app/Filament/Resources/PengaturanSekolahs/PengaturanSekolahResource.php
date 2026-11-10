<?php

namespace App\Filament\Resources\PengaturanSekolahs;

use App\Filament\Resources\PengaturanSekolahs\Pages\ManagePengaturanSekolahs;
use App\Models\PengaturanSekolah;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\PembatasAkses;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PengaturanSekolahResource extends Resource
{
    use PembatasAkses;

    protected static ?string $modelLabel = 'Identitas Sekolah';
    protected static ?string $pluralModelLabel = 'Identitas Sekolah';
    protected static ?string $navigationLabel = 'Identitas Sekolah';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    // Hanya ada satu baris identitas: tidak bisa ditambah atau dihapus
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
    protected static ?string $model = PengaturanSekolah::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('instansi_1')->label('Baris instansi 1')->placeholder('PEMERINTAH PROVINSI JAWA TIMUR')->maxLength(255),
            TextInput::make('instansi_2')->label('Baris instansi 2')->placeholder('DINAS PENDIDIKAN')->maxLength(255),
            TextInput::make('nama_sekolah')->label('Nama sekolah')->required()->maxLength(255),
            TextInput::make('alamat')->maxLength(255),
            TextInput::make('telepon')->maxLength(50),
            TextInput::make('website')->maxLength(255),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('kota')
                ->label('Kota (untuk tanggal tanda tangan)')
                ->required()
                ->maxLength(80),

            FileUpload::make('kop_gambar')
                ->label('Gambar kop (opsional, disarankan)')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg'])
                ->maxSize(2048)
                ->disk('local')
                ->directory('kop')
                ->visibility('private')
                ->helperText('Unggah gambar kop lengkap (logo, nama, alamat, dan garis) selebar halaman. Kalau diisi, gambar ini dipakai sebagai kop PDF. Kalau kosong, kop dibuat dari teks di atas.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_sekolah')->label('Sekolah'),
                TextColumn::make('kota'),
                IconColumn::make('kop_gambar')
                    ->label('Gambar kop')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->kop_gambar)),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePengaturanSekolahs::route('/'),
        ];
    }
}
