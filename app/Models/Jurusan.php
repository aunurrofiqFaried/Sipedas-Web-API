<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jurusan extends Model
{
    protected $table = 'jurusan';

    protected $fillable = ['kode', 'nama', 'kaprodi_id'];

    // Kaprodi = guru yang memimpin jurusan ini (seperti wali kelas, bukan role tersendiri)
    public function kaprodi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kaprodi_id');
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function ruang(): HasMany
    {
        return $this->hasMany(Ruang::class);
    }
}
