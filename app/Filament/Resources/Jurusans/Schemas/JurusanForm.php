<?php

namespace App\Filament\Resources\Jurusans\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class JurusanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')
                ->required()
                ->maxLength(10)
                ->unique(ignoreRecord: true),

            TextInput::make('nama')
                ->required()
                ->maxLength(255),

            Select::make('kaprodi_id')
                ->label('Kaprodi')
                ->relationship(
                    'kaprodi',
                    'name',
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereNotIn('role', [User::ROLE_ADMIN, User::ROLE_STAF])
                        ->orderBy('name'),
                )
                ->searchable()
                ->preload()
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => 'Orang ini sudah menjadi kaprodi di jurusan lain.'])
                ->helperText('Kaprodi bisa memantau data jurusannya di aplikasi. Kosongkan bila belum ada.'),
        ]);
    }
}