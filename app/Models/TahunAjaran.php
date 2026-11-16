<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\DilindungiRiwayat;

class TahunAjaran extends Model
{
    use DilindungiRiwayat;
    protected $table = 'tahun_ajaran';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai', 'is_active'];

    protected static function booted(): void
    {
        static::saved(function (TahunAjaran $tahunAjaran) {
            if ($tahunAjaran->is_active) {
                static::where('id', '!=', $tahunAjaran->id)->update(['is_active' => false]);
            }
        });
    }

    protected function alasanKhususHapus(): ?string
    {
        return $this->is_active ? 'Tahun ajaran yang sedang aktif tidak bisa dihapus.' : null;
    }

    protected function penghalangHapus(): array
    {
        return [
            'kelas' => $this->hitung('kelas', 'tahun_ajaran_id'),
            'jadwal pelajaran' => $this->hitung('jadwal', 'tahun_ajaran_id'),
            'jadwal piket' => $this->hitung('jadwal_piket', 'tahun_ajaran_id'),
            'catatan pelanggaran' => $this->hitung('pelanggaran_siswa', 'tahun_ajaran_id'),
            'surat peringatan' => $this->hitung('sp_siswa', 'tahun_ajaran_id'),
            'catatan remisi' => $this->hitung('remisi_siswa', 'tahun_ajaran_id'),
        ];
    }

    protected function saranHapus(): string
    {
        return 'Tahun ajaran lama sebaiknya disimpan sebagai arsip.';
    }

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function aktif(): ?self
    {
        return static::query()->aktif()->first();
    }
}
