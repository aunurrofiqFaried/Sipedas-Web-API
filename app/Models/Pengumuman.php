<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    public const TINGKAT = ['biasa' => 'Biasa', 'penting' => 'Penting'];

    protected $fillable = ['judul', 'isi', 'tingkat', 'berlaku_sampai', 'is_aktif', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'berlaku_sampai' => 'date',
            'is_aktif' => 'boolean',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // Pengumuman yang aktif dan belum kedaluwarsa
    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('is_aktif', true)
            ->where(fn ($w) => $w->whereNull('berlaku_sampai')
                ->orWhere('berlaku_sampai', '>=', now()->toDateString()));
    }
}