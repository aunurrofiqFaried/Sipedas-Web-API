<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class KalenderAkademik extends Model
{
    protected $table = 'kalender_akademik';

    public const JENIS = [
        'libur' => 'Libur (tidak ada kegiatan belajar)',
        'peringatan' => 'Peringatan (kegiatan belajar tetap berjalan)',
    ];

    protected $fillable = ['judul', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    // Semua acara yang mencakup tanggal tertentu
    public static function pada(Carbon|string $tanggal): Collection
    {
        $t = Carbon::parse($tanggal)->toDateString();

        return static::where('tanggal_mulai', '<=', $t)
            ->where('tanggal_selesai', '>=', $t)
            ->orderBy('tanggal_mulai')
            ->get();
    }

    public static function liburPada(Carbon|string $tanggal): ?self
    {
        return static::pada($tanggal)->firstWhere('jenis', 'libur');
    }

    public function ringkas(): array
    {
        return [
            'judul' => $this->judul,
            'keterangan' => $this->keterangan,
            'tanggal_mulai' => $this->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai->toDateString(),
        ];
    }
}