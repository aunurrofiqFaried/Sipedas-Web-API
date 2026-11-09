<?php

namespace App\Filament\Resources\JenisPelanggarans;

use App\Filament\Resources\JenisPelanggarans\Pages\CreateJenisPelanggaran;
use App\Filament\Resources\JenisPelanggarans\Pages\EditJenisPelanggaran;
use App\Filament\Resources\JenisPelanggarans\Pages\ListJenisPelanggarans;
use App\Filament\Resources\JenisPelanggarans\Schemas\JenisPelanggaranForm;
use App\Filament\Resources\JenisPelanggarans\Tables\JenisPelanggaransTable;
use App\Models\JenisPelanggaran;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\PembatasAkses;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class JenisPelanggaranResource extends Resource
{
    use PembatasAkses;

    protected static ?string $modelLabel = 'Jenis Pelanggaran';
    protected static ?string $pluralModelLabel = 'Jenis Pelanggaran';
    protected static ?string $navigationLabel = 'Jenis Pelanggaran';
    protected static string|UnitEnum|null $navigationGroup = 'Kesiswaan';

    // Waka kesiswaan (dan admin) yang mengatur; guru BK dan kepala sekolah melihat
    protected static function rolePelihat(): array
    {
        return [User::ROLE_GURU_BK, User::ROLE_KEPALA_SEKOLAH];
    }

    protected static function roleEditor(): array
    {
        return [User::ROLE_KESISWAAN];
    }

    // Jenis yang sudah dipakai tidak boleh dihapus (riwayat siswa merujuk padanya); nonaktifkan lewat toggle
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
    protected static ?string $model = JenisPelanggaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return JenisPelanggaranForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JenisPelanggaransTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJenisPelanggarans::route('/'),
            'create' => CreateJenisPelanggaran::route('/create'),
            'edit' => EditJenisPelanggaran::route('/{record}/edit'),
        ];
    }
}
