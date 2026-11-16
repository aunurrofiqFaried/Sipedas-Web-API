<?php

namespace App\Http\Controllers\Api\Pimpinan;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\KepatuhanJurnal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use App\Support\LingkupJurusan;
use Illuminate\Http\Request;

class DataGuruController extends Controller
{
    // Admin dan staf tidak termasuk daftar guru
    private const DIKECUALIKAN = [User::ROLE_ADMIN, User::ROLE_STAF];

    private const LABEL_PERAN = [
        User::ROLE_GURU => 'Guru',
        User::ROLE_GURU_BK => 'Guru BK',
        User::ROLE_KESISWAAN => 'Waka Kesiswaan',
        User::ROLE_KEPALA_SEKOLAH => 'Kepala Sekolah',
        User::ROLE_WAKA_KURIKULUM => 'Waka Kurikulum',
        User::ROLE_WAKA_SDM => 'Waka SDM',
    ];

    // GET /api/pimpinan/guru
    public function index(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $jurusanId = LingkupJurusan::untuk($request->user());

        // Kaprodi: hanya sesi dan kelas di jurusannya yang dihitung
        $jadwal = Jadwal::where('tahun_ajaran_id', $ta->id)
            ->when($jurusanId, fn ($q) => $q->whereHas('kelas', fn ($k) => $k->where('jurusan_id', $jurusanId)))
            ->get(['guru_id', 'kelas_id', 'mapel_id', 'hari', 'jam_ke_mulai', 'jam_ke_selesai'])
            ->groupBy('guru_id');

        $piket = JadwalPiket::where('tahun_ajaran_id', $ta->id)
            ->orderBy('hari')
            ->orderBy('jam_ke_mulai')
            ->get()
            ->groupBy('user_id');

        $wali = Kelas::where('tahun_ajaran_id', $ta->id)
            ->whereNotNull('wali_kelas_id')
            ->when($jurusanId, fn ($q) => $q->where('jurusan_id', $jurusanId))
            ->orderBy('nama')
            ->get(['id', 'nama', 'wali_kelas_id'])
            ->groupBy('wali_kelas_id');

        // Kaprodi hanya melihat guru yang mengajar atau menjadi wali di jurusannya
        $guru = User::where('is_active', true)
            ->whereNotIn('role', self::DIKECUALIKAN)
            ->when($jurusanId, fn ($q) => $q->whereIn('id', $jadwal->keys()->merge($wali->keys())->unique()->values()))
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'role']);

        // Kepatuhan jurnal bulan ini (dihitung sampai kemarin), satu kali hitung untuk semua guru
        $kepatuhan = collect(KepatuhanJurnal::hitung(now()->startOfMonth(), now()->endOfMonth(), null, $jurusanId)['guru'])
            ->keyBy('id');

        $baris = $guru->map(function (User $u) use ($jadwal, $piket, $wali, $kepatuhan) {
            $j = $jadwal->get($u->id, collect());
            $kep = $kepatuhan->get($u->id);

            return [
                'id' => $u->id,
                'nama' => $u->name,
                'nip' => $u->nip,
                'role' => $u->role,
                'role_label' => self::LABEL_PERAN[$u->role] ?? $u->role,
                'jam_per_minggu' => (int) $j->sum(fn ($x) => $x->jam_ke_selesai - $x->jam_ke_mulai + 1),
                'sesi_per_minggu' => $j->count(),
                'jumlah_kelas' => $j->pluck('kelas_id')->unique()->count(),
                'jumlah_mapel' => $j->pluck('mapel_id')->unique()->count(),
                'wali_kelas' => $wali->get($u->id)?->pluck('nama')->values()->all() ?? [],
                'piket' => $this->bentukPiket($piket->get($u->id, collect())),
                'kepatuhan_persen' => $kep['persen'] ?? null,
                'kepatuhan_status' => $kep['status'] ?? null,
            ];
        })->values();

        $mengajar = $baris->where('jam_per_minggu', '>', 0);
        $tertinggi = $mengajar->sortByDesc('jam_per_minggu')->first();

        return response()->json([
            'ringkasan' => [
                'guru' => $baris->count(),
                'mengajar' => $mengajar->count(),
                'wali_kelas' => $baris->filter(fn ($b) => count($b['wali_kelas']) > 0)->count(),
                'piket' => $baris->filter(fn ($b) => count($b['piket']) > 0)->count(),
                'rata_jam' => $mengajar->count() > 0 ? round($mengajar->avg('jam_per_minggu'), 1) : null,
                'jam_tertinggi' => $tertinggi
                    ? ['nama' => $tertinggi['nama'], 'jam' => $tertinggi['jam_per_minggu']]
                    : null,
            ],
            'guru' => $baris->all(),
        ]);
    }

    // GET /api/pimpinan/guru/{guru}
    public function show(Request $request, User $guru): JsonResponse
    {
        abort_unless($guru->is_active && ! in_array($guru->role, self::DIKECUALIKAN, true), 404);

        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');
        $jurusanId = LingkupJurusan::untuk($request->user());

        $jadwal = Jadwal::where('tahun_ajaran_id', $ta->id)
            ->where('guru_id', $guru->id)
            ->when($jurusanId, fn ($q) => $q->whereHas('kelas', fn ($k) => $k->where('jurusan_id', $jurusanId)))
            ->with(['kelas:id,nama', 'mapel:id,nama', 'ruang:id,nama'])
            ->orderBy('hari')
            ->orderBy('jam_ke_mulai')
            ->get();

        $mingguan = $jadwal->groupBy('hari')->map(fn ($g, $hari) => [
            'hari' => (int) $hari,
            'label' => Jadwal::HARI[(int) $hari] ?? 'Hari ' . $hari,
            'jam' => (int) $g->sum(fn ($j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1),
            'sesi' => $g->map(fn ($j) => [
                'jam_ke_mulai' => $j->jam_ke_mulai,
                'jam_ke_selesai' => $j->jam_ke_selesai,
                'jam_mulai' => substr((string) $j->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
                'kelas' => $j->kelas?->nama,
                'mapel' => $j->mapel?->nama,
                'ruang' => $j->ruang?->nama,
            ])->values()->all(),
        ])->values()->all();

        $piket = JadwalPiket::where('tahun_ajaran_id', $ta->id)
            ->where('user_id', $guru->id)
            ->orderBy('hari')
            ->orderBy('jam_ke_mulai')
            ->get();

        $waliKelas = Kelas::where('tahun_ajaran_id', $ta->id)
            ->where('wali_kelas_id', $guru->id)
            ->when($jurusanId, fn ($q) => $q->where('jurusan_id', $jurusanId))
            ->orderBy('nama')
            ->pluck('nama')
            ->all();

        abort_if($jurusanId !== null && $jadwal->isEmpty() && $waliKelas === [], 403, 'Guru ini tidak terkait dengan jurusan Anda.');

        $kep = KepatuhanJurnal::hitung(now()->startOfMonth(), now()->endOfMonth(), $guru->id, $jurusanId);

        return response()->json([
            'guru' => [
                'id' => $guru->id,
                'nama' => $guru->name,
                'nip' => $guru->nip,
                'role' => $guru->role,
                'role_label' => self::LABEL_PERAN[$guru->role] ?? $guru->role,
            ],
            'mengajar' => [
                'jam_per_minggu' => (int) $jadwal->sum(fn ($j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1),
                'sesi_per_minggu' => $jadwal->count(),
                'jumlah_kelas' => $jadwal->pluck('kelas_id')->unique()->count(),
                'jumlah_mapel' => $jadwal->pluck('mapel_id')->unique()->count(),
            ],
            'jadwal_mingguan' => $mingguan,
            'piket' => $this->bentukPiket($piket),
            'wali_kelas' => $waliKelas,
            'kepatuhan' => [
                'periode' => $kep['periode'],
                'ringkasan' => $kep['guru'][0] ?? [
                    'sesi_per_minggu' => 0, 'seharusnya' => 0, 'terisi' => 0, 'terlewat' => 0,
                    'persen' => null, 'status' => null, 'terakhir_isi' => null,
                ],
                'terlewat' => array_slice($kep['terlewat'], 0, 10),
            ],
        ]);
    }

    private function bentukPiket(Collection $piket): array
    {
        return $piket->map(fn (JadwalPiket $p) => [
            'hari' => (int) $p->hari,
            'hari_label' => Jadwal::HARI[(int) $p->hari] ?? 'Hari ' . $p->hari,
            'jam' => $p->labelJam(),
            'tugas' => $p->tugas,
        ])->values()->all();
    }
}