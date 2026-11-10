<?php

namespace App\Support;

use App\Models\PengaturanSekolah;
use App\Models\User;

class PdfData
{
    // Data yang dipakai semua PDF: identitas sekolah, kop, kepala sekolah, tanggal cetak
    public static function umum(bool $mendatar = false): array
    {
        $sekolah = PengaturanSekolah::ambil();

        // Lebar kop dalam persen. Kertas mendatar lebih lebar, jadi dikecilkan
        // supaya ukuran fisik kop sama dengan di kertas tegak.
        $persen = max(30, min(100, (int) ($sekolah->kop_lebar ?: 70)));

        return [
            'sekolah' => $sekolah,
            'kopGambar' => $sekolah->kopDataUri(),
            'lebarKop' => $mendatar ? (int) round($persen * 0.68) : $persen,
            'kepsek' => User::where('role', User::ROLE_KEPALA_SEKOLAH)
                ->where('is_active', true)
                ->orderBy('id')
                ->first(),
            'tanggalCetak' => now()->locale('id')->translatedFormat('j F Y'),
        ];
    }
}