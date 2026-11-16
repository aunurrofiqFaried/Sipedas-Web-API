<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Siswa extends Model
{
    protected $table = 'siswa';

    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    public const STATUS_AKTIF = 'aktif';
    public const STATUS_LULUS = 'lulus';
    public const STATUS_PINDAH = 'pindah';
    public const STATUS_KELUAR = 'keluar';

    public const STATUS = [
        self::STATUS_AKTIF => 'Aktif',
        self::STATUS_LULUS => 'Lulus',
        self::STATUS_PINDAH => 'Pindah',
        self::STATUS_KELUAR => 'Keluar',
    ];

    protected $fillable = [
        'nis', 'nisn', 'nama', 'jenis_kelamin', 'agama',
        'nama_wali', 'hubungan_wali', 'no_hp_wali',
        'is_active', 'status', 'tanggal_keluar', 'keterangan_keluar',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tanggal_keluar' => 'date'];
    }

    // is_active dan status selalu sejalan: is_active = (status == aktif).
    // Seluruh API dan widget masih memakai is_active, jadi kolom itu tetap dipertahankan.
    protected static function booted(): void
    {
        static::saving(function (Siswa $siswa) {
            if ($siswa->isDirty('status')) {
                $siswa->is_active = $siswa->status === self::STATUS_AKTIF;
            } elseif ($siswa->isDirty('is_active')) {
                // Cara lama (saklar aktif): nonaktif tanpa alasan dianggap keluar
                if ($siswa->is_active) {
                    $siswa->status = self::STATUS_AKTIF;
                } elseif (in_array($siswa->status, [null, self::STATUS_AKTIF], true)) {
                    $siswa->status = self::STATUS_KELUAR;
                }
            }

            if ($siswa->status === self::STATUS_AKTIF) {
                $siswa->tanggal_keluar = null;
                $siswa->keterangan_keluar = null;
            }
        });
    }

    public function kelasSiswa(): HasMany
    {
        return $this->hasMany(KelasSiswa::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function kelasSiswaAktif(): HasOne
    {
        return $this->hasOne(KelasSiswa::class)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_active', true));
    }

    public function pelanggaran(): HasMany
    {
        return $this->hasMany(PelanggaranSiswa::class);
    }

    public function spSiswa(): HasMany
    {
        return $this->hasMany(SpSiswa::class);
    }

    public function remisi(): HasMany
    {
        return $this->hasMany(RemisiSiswa::class);
    }

    public function totalPelanggaran(int $tahunAjaranId): int
    {
        return (int) $this->pelanggaran()
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->sum('poin');
    }

    public function totalRemisi(int $tahunAjaranId): int
    {
        return (int) $this->remisi()
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->sum('poin');
    }

    // Poin aktif = pelanggaran dikurangi remisi, tidak pernah di bawah nol
    public function totalPoin(int $tahunAjaranId): int
    {
        return max(0, $this->totalPelanggaran($tahunAjaranId) - $this->totalRemisi($tahunAjaranId));
    }
}
