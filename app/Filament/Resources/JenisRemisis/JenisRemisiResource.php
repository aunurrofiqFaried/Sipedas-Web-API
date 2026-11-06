<?php

namespace App\Filament\Resources\JenisRemisis;

use App\Filament\Resources\JenisRemisis\Pages\CreateJenisRemisi;
use App\Filament\Resources\JenisRemisis\Pages\EditJenisRemisi;
use App\Filament\Resources\JenisRemisis\Pages\ListJenisRemisis;
use App\Filament\Resources\JenisRemisis\Schemas\JenisRemisiForm;
use App\Filament\Resources\JenisRemisis\Tables\JenisRemisisTable;
use App\Models\JenisRemisi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\PembatasAkses;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class JenisRemisiResource extends Resource
{
    use PembatasAkses;

    protected static ?string $modelLabel = 'Jenis Remisi';
    protected static ?string $pluralModelLabel = 'Jenis Remisi';
    protected static ?string $navigationLabel = 'Jenis Remisi';
    protected static string|UnitEnum|null $navigationGroup = 'Kesiswaan';

    protected static function rolePelihat(): array
    {
        return [User::ROLE_GURU_BK, User::ROLE_KEPALA_SEKOLAH];
    }

    protected static function roleEditor(): array
    {
        return [User::ROLE_KESISWAAN];
    }

    // Jenis yang sudah dipakai tidak boleh dihapus; nonaktifkan saja lewat toggle
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
    protected static ?string $model = JenisRemisi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return JenisRemisiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JenisRemisisTable::configure($table);
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
            'index' => ListJenisRemisis::route('/'),
            'create' => CreateJenisRemisi::route('/create'),
            'edit' => EditJenisRemisi::route('/{record}/edit'),
        ];
    }
}
