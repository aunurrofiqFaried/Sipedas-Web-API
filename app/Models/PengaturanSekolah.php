<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PengaturanSekolah extends Model
{
    protected $table = 'pengaturan_sekolah';

    protected $fillable = [
        'instansi_1', 'instansi_2', 'nama_sekolah', 'alamat',
        'telepon', 'website', 'email', 'kota', 'kop_gambar', 'kop_lebar',
    ];

    public static function ambil(): self
    {
        return static::query()->first() ?? new static(['nama_sekolah' => 'Nama Sekolah', 'kota' => '']);
    }

    // Gambar kop sebagai data URI, supaya Dompdf tidak perlu membuka berkas atau jaringan
    public function kopDataUri(): ?string
    {
        $disk = Storage::disk('local');

        if (blank($this->kop_gambar) || ! $disk->exists($this->kop_gambar)) {
            return null;
        }

        $mime = $disk->mimeType($this->kop_gambar) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($disk->get($this->kop_gambar));
    }
}