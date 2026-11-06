<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisRemisi extends Model
{
    protected $table = 'jenis_remisi';

    protected $fillable = ['nama', 'poin', 'keterangan', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }
}