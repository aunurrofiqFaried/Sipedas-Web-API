<?php

namespace App\Support;

use App\Models\User;

// Menentukan data jurusan mana yang boleh dilihat di menu pimpinan.
// Pimpinan melihat semua jurusan; kaprodi hanya jurusannya sendiri.
class LingkupJurusan
{
    /**
     * @return int|null  null = semua jurusan, angka = hanya jurusan itu
     */
    public static function untuk(User $user): ?int
    {
        if ($user->isPimpinan()) {
            return null;
        }

        $jurusanId = $user->jurusanDipimpin?->id;

        abort_if($jurusanId === null, 403, 'Anda tidak memiliki akses.');

        return (int) $jurusanId;
    }
}