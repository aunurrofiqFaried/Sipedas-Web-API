<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait PembatasAkses
{
    // Role (selain admin) yang boleh MEMBUKA menu dan melihat data
    protected static function rolePelihat(): array
    {
        return [];
    }

    // Role (selain admin) yang boleh menambah, mengubah, dan menghapus
    protected static function roleEditor(): array
    {
        return [];
    }

    protected static function punyaRole(array $roles): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        return $user->punyaPeran(User::ROLE_ADMIN, ...$roles);
    }

    public static function canAccess(): bool
    {
        return static::punyaRole([...static::rolePelihat(), ...static::roleEditor()]);
    }

    public static function canCreate(): bool
    {
        return static::punyaRole(static::roleEditor());
    }

    public static function canEdit(Model $record): bool
    {
        return static::punyaRole(static::roleEditor());
    }

    public static function canDelete(Model $record): bool
    {
        return static::punyaRole(static::roleEditor());
    }

    public static function canDeleteAny(): bool
    {
        return static::punyaRole(static::roleEditor());
    }
}