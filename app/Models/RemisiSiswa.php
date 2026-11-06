<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemisiSiswa extends Model
{
    protected $table = 'remisi_siswa';

    protected $fillable = [
        'siswa_id', 'tahun_ajaran_id', 'jenis_remisi_id',
        'poin', 'tanggal', 'keterangan', 'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisRemisi::class, 'jenis_remisi_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}