<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\DilindungiRiwayat;

class Kelas extends Model
{
    use DilindungiRiwayat;
    protected $table = 'kelas';

    protected $fillable = ['tahun_ajaran_id', 'nama', 'tingkat', 'jurusan_id', 'wali_kelas_id'];

    protected function penghalangHapus(): array
    {
        return [
            'siswa di kelas ini' => $this->hitung('kelas_siswa', 'kelas_id'),
            'catatan absensi' => $this->hitung('absensi', 'kelas_id'),
            'catatan sholat' => $this->hitung('absensi_sholat', 'kelas_id'),
            'jurnal mengajar' => $this->hitung('jurnal', 'kelas_id'),
            'jadwal pelajaran' => $this->hitung('jadwal', 'kelas_id'),
        ];
    }

    protected function saranHapus(): string
    {
        return 'Kelas yang sudah dipakai sebaiknya dibiarkan sebagai arsip tahun ajarannya.';
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'kelas_siswa', 'kelas_id', 'siswa_id')
            ->withPivot('tahun_ajaran_id')
            ->withTimestamps();
    }
}
