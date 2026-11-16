<?php

namespace App\Filament\Resources\JamPelajarans;

use App\Filament\Resources\JamPelajarans\Pages\CreateJamPelajaran;
use App\Filament\Resources\JamPelajarans\Pages\EditJamPelajaran;
use App\Filament\Resources\JamPelajarans\Pages\ListJamPelajarans;
use App\Filament\Resources\JamPelajarans\Schemas\JamPelajaranForm;
use App\Filament\Resources\JamPelajarans\Tables\JamPelajaransTable;
use App\Models\JamPelajaran;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class JamPelajaranResource extends Resource
{
    protected static ?string $model = JamPelajaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Jam Pelajaran';
    protected static ?string $pluralModelLabel = 'Jam Pelajaran';
    protected static ?string $navigationLabel = 'Pengaturan Jam Pelajaran';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->punyaPeran(User::ROLE_ADMIN);
    }

    // Jam yang sudah dipakai jadwal tidak boleh dihapus
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return JamPelajaranForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JamPelajaransTable::configure($table);
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
            'index' => ListJamPelajarans::route('/'),
            'create' => CreateJamPelajaran::route('/create'),
            'edit' => EditJamPelajaran::route('/{record}/edit'),
        ];
    }
}
