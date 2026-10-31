<?php

namespace App\Filament\Resources\Pengumumen;

use App\Filament\Resources\Pengumumen\Pages\CreatePengumuman;
use App\Filament\Resources\Pengumumen\Pages\EditPengumuman;
use App\Filament\Resources\Pengumumen\Pages\ListPengumumen;
use App\Filament\Resources\Pengumumen\Schemas\PengumumanForm;
use App\Filament\Resources\Pengumumen\Tables\PengumumenTable;
use App\Models\Pengumuman;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\PembatasAkses;
use App\Models\User;
use UnitEnum;

class PengumumanResource extends Resource
{
    use PembatasAkses;

    protected static ?string $modelLabel = 'Pengumuman';
    protected static ?string $pluralModelLabel = 'Pengumuman';
    protected static ?string $navigationLabel = 'Pengumuman';
    protected static string|UnitEnum|null $navigationGroup = 'Informasi';

    // Yang boleh membuat pengumuman: waka kesiswaan dan kepala sekolah (admin otomatis)
    protected static function roleEditor(): array
    {
        return [User::ROLE_KESISWAAN, User::ROLE_KEPALA_SEKOLAH];
    }
    protected static ?string $model = Pengumuman::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PengumumanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengumumenTable::configure($table);
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
            'index' => ListPengumumen::route('/'),
            'create' => CreatePengumuman::route('/create'),
            'edit' => EditPengumuman::route('/{record}/edit'),
        ];
    }
}
