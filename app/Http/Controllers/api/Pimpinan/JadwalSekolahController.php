<?php

namespace App\Http\Controllers\Api\Pimpinan;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class JadwalSekolahController extends Controller
{
    // Admin dan staf tidak mengajar
    private const DIKECUALIKAN = [User::ROLE_ADMIN, User::ROLE_STAF];

    // GET /api/pimpinan/jadwal/pilihan
    // Bahan untuk pemilih: jam pelajaran, hari, kelas, dan guru yang punya jadwal
    public function pilihan(): JsonResponse
    {
        $ta = $this->tahunAjaran();

        $guruIds = Jadwal::where('tahun_ajaran_id', $ta->id)->distinct()->pluck('guru_id');

        return response()->json([
            'jam' => $this->daftarJam(),
            'hari' => $this->daftarHari(),
            'hari_ini' => $this->hariIni(),
            'kelas' => Kelas::with('jurusan:id,nama')
                ->where('tahun_ajaran_id', $ta->id)
                ->orderBy('nama')
                ->get(['id', 'nama', 'jurusan_id'])
                ->map(fn (Kelas $k) => [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'jurusan' => $k->jurusan?->nama ?? 'Tanpa jurusan',
                ])
                ->values(),
            'guru' => User::whereIn('id', $guruIds)
                ->orderBy('name')
                ->get(['id', 'name', 'nip'])
                ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name, 'nip' => $u->nip])
                ->values(),
        ]);
    }

    // GET /api/pimpinan/jadwal/kelas/{kelas}
    // Jadwal mingguan satu kelas
    public function kelas(Kelas $kelas): JsonResponse
    {
        $ta = $this->tahunAjaran();
        abort_unless((int) $kelas->tahun_ajaran_id === (int) $ta->id, 404);

        $kelas->load(['waliKelas:id,name', 'jurusan:id,nama']);

        $jadwal = $this->ambil($ta, fn ($q) => $q->where('kelas_id', $kelas->id));

        return response()->json([
            'kelas' => [
                'id' => $kelas->id,
                'nama' => $kelas->nama,
                'jurusan' => $kelas->jurusan?->nama,
                'wali_kelas' => $kelas->waliKelas?->name,
            ],
            'jam' => $this->daftarJam(),
            'ringkasan' => $this->ringkasan($jadwal),
            'hari' => $this->perHari($jadwal),
        ]);
    }

    // GET /api/pimpinan/jadwal/guru/{guru}
    // Jadwal mingguan satu guru, beserta jadwal piketnya
    public function guru(User $guru): JsonResponse
    {
        abort_unless($guru->is_active && ! in_array($guru->role, self::DIKECUALIKAN, true), 404);

        $ta = $this->tahunAjaran();
        $jadwal = $this->ambil($ta, fn ($q) => $q->where('guru_id', $guru->id));
        $jam = JamPelajaran::semua();

        $piket = JadwalPiket::where('tahun_ajaran_id', $ta->id)
            ->where('user_id', $guru->id)
            ->orderBy('hari')
            ->orderBy('jam_ke_mulai')
            ->get()
            ->map(fn (JadwalPiket $p) => $this->bentukPiket($p, $jam))
            ->values();

        return response()->json([
            'guru' => ['id' => $guru->id, 'nama' => $guru->name, 'nip' => $guru->nip],
            'jam' => $this->daftarJam(),
            'ringkasan' => $this->ringkasan($jadwal),
            'hari' => $this->perHari($jadwal),
            'piket' => $piket,
        ]);
    }

    // GET /api/pimpinan/jadwal/hari/{hari}
    // Jadwal induk: semua kelas pada satu hari (1 = Senin ... 5 = Jumat)
    public function hari(int $hari): JsonResponse
    {
        abort_unless(isset(Jadwal::HARI[$hari]), 404, 'Hari tidak dikenal.');

        $ta = $this->tahunAjaran();
        $jam = JamPelajaran::semua();

        $jadwal = $this->ambil($ta, fn ($q) => $q->where('hari', $hari));

        $adalahHariIni = $hari === now()->dayOfWeekIso;
        $libur = $adalahHariIni ? KalenderAkademik::liburPada(now()) : null;
        $jamSekarang = now()->format('H:i');

        // Hanya kelas yang punya jadwal pada hari itu
        $kelas = Kelas::with('jurusan:id,nama')
            ->where('tahun_ajaran_id', $ta->id)
            ->whereIn('id', $jadwal->pluck('kelas_id')->unique())
            ->orderBy('nama')
            ->get(['id', 'nama', 'jurusan_id']);

        $perKelas = $jadwal->groupBy('kelas_id');

        $piket = JadwalPiket::with('user:id,name')
            ->where('tahun_ajaran_id', $ta->id)
            ->where('hari', $hari)
            ->whereNotNull('jam_ke_mulai')
            ->orderBy('jam_ke_mulai')
            ->get()
            ->map(fn (JadwalPiket $p) => $this->bentukPiket($p, $jam) + ['guru' => $p->user?->name])
            ->values();

        return response()->json([
            'hari' => $hari,
            'label' => Jadwal::HARI[$hari],
            'hari_ini' => $adalahHariIni,
            'libur' => $libur?->judul,
            'jam_sekarang' => $adalahHariIni ? $jamSekarang : null,
            'jam' => $this->daftarJam(),
            'ringkasan' => $this->ringkasan($jadwal) + [
                'kelas' => $kelas->count(),
                'guru' => $jadwal->pluck('guru_id')->unique()->count(),
            ],
            'kelas' => $kelas->map(fn (Kelas $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'jurusan' => $k->jurusan?->nama ?? 'Tanpa jurusan',
                'sesi' => $perKelas->get($k->id, collect())
                    ->sortBy('jam_ke_mulai')
                    ->map(fn (Jadwal $j) => $this->bentukSesi($j) + [
                        'berlangsung' => $adalahHariIni && ! $libur
                            && substr((string) $j->jam_mulai, 0, 5) <= $jamSekarang
                            && substr((string) $j->jam_selesai, 0, 5) > $jamSekarang,
                    ])
                    ->values(),
            ])->values(),
            'piket' => $piket,
        ]);
    }

    private function tahunAjaran(): TahunAjaran
    {
        return TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
    }

    // Jadwal tahun ajaran aktif beserta relasinya; $saring menambahkan syarat
    private function ambil(TahunAjaran $ta, callable $saring): Collection
    {
        $q = Jadwal::with(['kelas:id,nama', 'mapel:id,nama', 'guru:id,name', 'ruang:id,nama'])
            ->where('tahun_ajaran_id', $ta->id);

        $saring($q);

        return $q->orderBy('hari')->orderBy('jam_ke_mulai')->get();
    }

    private function bentukSesi(Jadwal $j): array
    {
        return [
            'id' => $j->id,
            'jam_ke_mulai' => (int) $j->jam_ke_mulai,
            'jam_ke_selesai' => (int) $j->jam_ke_selesai,
            'jam_mulai' => substr((string) $j->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
            'kelas' => $j->kelas?->nama,
            'mapel' => $j->mapel?->nama,
            'guru' => $j->guru?->name,
            'guru_id' => $j->guru_id,
            'ruang' => $j->ruang?->nama,
        ];
    }

    private function bentukPiket(JadwalPiket $p, Collection $jam): array
    {
        return [
            'hari' => (int) $p->hari,
            'hari_label' => Jadwal::HARI[(int) $p->hari] ?? 'Hari ' . $p->hari,
            'jam_ke_mulai' => (int) $p->jam_ke_mulai,
            'jam_ke_selesai' => (int) $p->jam_ke_selesai,
            'jam_mulai' => substr((string) $jam->get($p->jam_ke_mulai)?->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $jam->get($p->jam_ke_selesai)?->jam_selesai, 0, 5),
            'tugas' => $p->tugas,
        ];
    }

    // Jadwal mingguan dikelompokkan per hari; semua hari tetap muncul walau kosong
    private function perHari(Collection $jadwal): array
    {
        $grup = $jadwal->groupBy('hari');

        return collect(Jadwal::HARI)->map(function (string $label, int $hari) use ($grup) {
            $g = $grup->get($hari, collect());

            return [
                'hari' => $hari,
                'label' => $label,
                'jam' => (int) $g->sum(fn (Jadwal $j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1),
                'sesi' => $g->map(fn (Jadwal $j) => $this->bentukSesi($j))->values()->all(),
            ];
        })->values()->all();
    }

    private function ringkasan(Collection $jadwal): array
    {
        return [
            'sesi' => $jadwal->count(),
            'jam' => (int) $jadwal->sum(fn (Jadwal $j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1),
            'mapel' => $jadwal->pluck('mapel_id')->unique()->count(),
        ];
    }

    private function daftarJam(): array
    {
        return JamPelajaran::semua()
            ->sortBy('jam_ke')
            ->map(fn (JamPelajaran $j) => [
                'jam_ke' => (int) $j->jam_ke,
                'jam_mulai' => substr((string) $j->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
            ])
            ->values()
            ->all();
    }

    private function daftarHari(): array
    {
        return collect(Jadwal::HARI)
            ->map(fn (string $label, int $hari) => ['hari' => $hari, 'label' => $label])
            ->values()
            ->all();
    }

    // Hari efektif untuk pemilih awal: hari ini bila Senin-Jumat, selain itu Senin
    private function hariIni(): int
    {
        $h = now()->dayOfWeekIso;

        return isset(Jadwal::HARI[$h]) ? $h : 1;
    }
}