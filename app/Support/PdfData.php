<?php

namespace App\Support;

use App\Models\PengaturanSekolah;
use App\Models\User;

class PdfData
{
    // Data yang dipakai semua PDF: identitas sekolah, kop, kepala sekolah, tanggal cetak
    public static function umum(): array
    {
        $sekolah = PengaturanSekolah::ambil();

        return [
            'sekolah' => $sekolah,
            'kopGambar' => $sekolah->kopDataUri(),
            'kepsek' => User::where('role', User::ROLE_KEPALA_SEKOLAH)
                ->where('is_active', true)
                ->orderBy('id')
                ->first(),
            'tanggalCetak' => now()->locale('id')->translatedFormat('j F Y'),
        ];
    }
}