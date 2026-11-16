<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Models\Concerns\DilindungiRiwayat;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, DilindungiRiwayat;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_GURU = 'guru';
    public const ROLE_GURU_BK = 'guru_bk';
    public const ROLE_KESISWAAN = 'kesiswaan';
    public const ROLE_KEPALA_SEKOLAH = 'kepala_sekolah';
    public const ROLE_STAF = 'staf';
    public const ROLE_WAKA_KURIKULUM = 'waka_kurikulum';
    public const ROLE_WAKA_SDM = 'waka_sdm';

    // Peran yang boleh memantau data kesiswaan seluruh sekolah (SP, rekap sholat, grafik dashboard)
    public const PERAN_PEMANTAU = ['admin', 'kesiswaan', 'kepala_sekolah'];

    // Peran yang boleh melihat data seluruh sekolah dan memantau guru
    public const PERAN_PIMPINAN = ['admin', 'kepala_sekolah', 'waka_kurikulum', 'kesiswaan', 'waka_sdm'];


    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
        self::ROLE_GURU_BK,
        self::ROLE_KESISWAAN,
        self::ROLE_KEPALA_SEKOLAH,
        self::ROLE_WAKA_KURIKULUM,
        self::ROLE_WAKA_SDM,
        self::ROLE_STAF,
    ];

    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_GURU => 'Guru',
        self::ROLE_GURU_BK => 'Guru BK',
        self::ROLE_KESISWAAN => 'Waka Kesiswaan',
        self::ROLE_KEPALA_SEKOLAH => 'Kepala Sekolah',
        self::ROLE_WAKA_KURIKULUM => 'Waka Kurikulum',
        self::ROLE_WAKA_SDM => 'Waka SDM',
        self::ROLE_STAF => 'Staf',
    ];

    // Peran utama ada di kolom role (menentukan label dan daftar guru); peran tambahan di peran_tambahan.
    // Hak akses dihitung dari gabungan keduanya.
    public function semuaPeran(): array
    {
        return array_values(array_unique(array_filter([$this->role, ...($this->peran_tambahan ?? [])])));
    }

    public function punyaPeran(string ...$peran): bool
    {
        return array_intersect($peran, $this->semuaPeran()) !== [];
    }

    // User::denganPeran('guru', 'admin') mencakup peran utama dan peran tambahan
    public function scopeDenganPeran(Builder $query, string ...$peran): Builder
    {
        return $query->where(function (Builder $q) use ($peran) {
            $q->whereIn('role', $peran);

            foreach ($peran as $p) {
                $q->orWhereJsonContains('peran_tambahan', $p);
            }
        });
    }

    public function isPimpinan(): bool
    {
        return $this->punyaPeran(...self::PERAN_PIMPINAN);
    }

    protected $fillable = [
        'name', 'username', 'nip', 'email', 'password', 'role', 'peran_tambahan', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'peran_tambahan' => 'array',
        ];
    }

    // Jurusan yang dipimpin (null bila bukan kaprodi)
    public function jurusanDipimpin(): HasOne
    {
        return $this->hasOne(Jurusan::class, 'kaprodi_id');
    }

    public function isKaprodi(): bool
    {
        return $this->jurusanDipimpin()->exists();
    }

    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'guru_id');
    }

    // Wali kelas = guru yang menjadi wali di kelas pada tahun ajaran aktif
    public function isWaliKelas(): bool
    {
        return $this->kelasWali()
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_active', true))
            ->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->punyaPeran(
            self::ROLE_ADMIN,
            self::ROLE_KESISWAAN,
            self::ROLE_KEPALA_SEKOLAH,
            self::ROLE_GURU_BK,
        );
    }

    protected function penghalangHapus(): array
    {
        return [
            'jadwal mengajar' => $this->hitung('jadwal', 'guru_id'),
            'jurnal mengajar' => $this->hitung('jurnal', 'guru_id'),
            'jadwal piket' => $this->hitung('jadwal_piket', 'user_id'),
            'catatan absensi' => $this->hitung('absensi', 'diinput_oleh'),
            'catatan sholat' => $this->hitung('absensi_sholat', 'diinput_oleh'),
            'catatan pelanggaran' => $this->hitung('pelanggaran_siswa', 'dicatat_oleh'),
            'catatan remisi' => $this->hitung('remisi_siswa', 'dicatat_oleh'),
            'catatan kontak wali' => $this->hitung('kontak_wali', 'dihubungi_oleh'),
        ];
    }

    protected function saranHapus(): string
    {
        return 'Nonaktifkan akunnya saja (matikan "aktif") agar riwayatnya tetap utuh.';
    }
}
