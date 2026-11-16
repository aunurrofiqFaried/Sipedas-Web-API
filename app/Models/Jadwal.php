<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use app\Models\Concerns\DilindungiRiwayat;

class Jadwal extends Model
{
    use DilindungiRiwayat;
    protected function penghalangHapus(): array
    {
        return [
            'jurnal mengajar' => $this->hitung('jurnal', 'jadwal_id'),
        ];
    }

    protected function saranHapus(): string
    {
        return 'Ubah jadwalnya (bukan dihapus) agar jurnal yang sudah diisi tetap tersimpan.';
    }
    
    protected $table = 'jadwal';

    protected $fillable = [
        'tahun_ajaran_id', 'kelas_id', 'mapel_id', 'guru_id', 'ruang_id',
        'hari', 'jam_ke_mulai', 'jam_ke_selesai', 'jam_mulai', 'jam_selesai',
    ];

    public const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        // 6 => 'Sabtu',
    ];

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    public function jurnal(): HasMany
    {
        return $this->hasMany(Jurnal::class);
    }

    // hari: 1 = Senin ... 7 = Minggu (sama dengan Carbon::dayOfWeekIso)
    public function scopeHari(Builder $query, int $hari): Builder
    {
        return $query->where('hari', $hari);
    }
}
