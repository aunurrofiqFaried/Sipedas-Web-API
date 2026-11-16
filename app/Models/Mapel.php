<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\DilindungiRiwayat;

class Mapel extends Model
{
    use DilindungiRiwayat;
    protected $table = 'mapel';

    protected $fillable = ['kode', 'nama'];

    protected function penghalangHapus(): array
    {
        return [
            'jadwal pelajaran' => $this->hitung('jadwal', 'mapel_id'),
        ];
    }

    protected function saranHapus(): string
    {
        return 'Ganti dulu mapel pada jadwal tersebut. Jurnal ikut terhapus bila jadwalnya terhapus.';
    }
}
