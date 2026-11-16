<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

// Mencegah penghapusan baris yang akan ikut menghapus riwayat (absensi, jurnal, pelanggaran, dll.)
// karena relasinya memakai cascadeOnDelete. Dipakai bersama aturan di AppServiceProvider
// yang menampilkan alasan penolakan di panel Filament.
trait DilindungiRiwayat
{
    // Data yang ikut terhapus bila baris ini dihapus: ['label' => jumlah]
    abstract protected function penghalangHapus(): array;

    // Kalimat saran yang ditampilkan setelah rincian data
    protected function saranHapus(): string
    {
        return '';
    }

    // Alasan khusus di luar hitungan data (contoh: tahun ajaran masih aktif)
    protected function alasanKhususHapus(): ?string
    {
        return null;
    }

    protected function hitung(string $tabel, string $kolom): int
    {
        return (int) DB::table($tabel)->where($kolom, $this->getKey())->count();
    }

    // null = aman dihapus
    public function alasanTidakBisaDihapus(): ?string
    {
        if ($khusus = $this->alasanKhususHapus()) {
            return $khusus;
        }

        $ada = array_filter($this->penghalangHapus());

        if ($ada === []) {
            return null;
        }

        $rincian = collect($ada)
            ->map(fn ($jumlah, $label) => number_format((int) $jumlah, 0, ',', '.') . ' ' . $label)
            ->implode(', ');

        return trim("Masih ada {$rincian} yang akan ikut terhapus. " . $this->saranHapus());
    }

    // Pengaman terakhir: berlaku untuk semua jalur penghapusan, bukan hanya panel Filament
    protected static function bootDilindungiRiwayat(): void
    {
        static::deleting(function ($model) {
            if ($model->alasanTidakBisaDihapus() !== null) {
                return false;
            }
        });
    }
}