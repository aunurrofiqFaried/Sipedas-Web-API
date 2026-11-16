<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\KelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Memindahkan siswa ke tahun ajaran baru: kelas X naik ke XI, XI ke XII, dan XII lulus.
// rencana() hanya menghitung (tidak mengubah data), jalankan() melaksanakannya dalam satu transaksi.
class KenaikanKelasService
{
    private const NAIK = ['X' => 'XI', 'XI' => 'XII'];

    // "X TKJ 1" -> "XI TKJ 1"; null bila nama tidak bisa dinaikkan secara otomatis
    public static function namaTujuan(string $nama, string $tingkat): ?string
    {
        $tingkat = strtoupper(trim($tingkat));
        $berikut = self::NAIK[$tingkat] ?? null;

        if ($berikut === null) {
            return null;
        }

        // Nama diawali tingkat: "X TKJ 1", "XI-RPL 2", "X"
        if (preg_match('/^(\s*)(XII|XI|X)(?=[\s\-_.]|$)(.*)$/i', $nama, $m) && strtoupper($m[2]) === $tingkat) {
            return $m[1] . $berikut . $m[3];
        }

        // Tingkat di tengah nama: "TKJ X 1"
        $baru = preg_replace('/(?<![A-Za-z0-9])' . $tingkat . '(?![A-Za-z0-9])/', $berikut, $nama, 1, $jumlah);

        return $jumlah > 0 ? $baru : null;
    }

    /**
     * @param  array{tinggal?: array<int>, buat_kelas_x?: bool, bawa_wali?: bool, tanggal_lulus?: ?string}  $opsi
     */
    public function rencana(TahunAjaran $asal, TahunAjaran $tujuan, array $opsi = []): array
    {
        $tinggal = array_flip(array_map('intval', $opsi['tinggal'] ?? []));
        $masalah = [];

        if ($asal->id === $tujuan->id) {
            $masalah[] = 'Tahun ajaran asal dan tujuan tidak boleh sama.';
        } elseif ($tujuan->tanggal_mulai->lte($asal->tanggal_mulai)) {
            $masalah[] = 'Tahun ajaran tujuan harus lebih baru daripada tahun ajaran asal.';
        }

        $kelasAsal = Kelas::where('tahun_ajaran_id', $asal->id)->orderBy('nama')->get();

        if ($kelasAsal->isEmpty()) {
            $masalah[] = 'Tahun ajaran asal belum memiliki kelas.';
        }

        // Siswa per kelas asal; yang sudah tidak aktif (pindah, keluar, lulus) dilewati
        $anggota = DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->where('ks.tahun_ajaran_id', $asal->id)
            ->get(['ks.kelas_id', 'ks.siswa_id', 's.is_active']);

        $dilewati = (int) $anggota->where('is_active', false)->count();
        $aktifPerKelas = $anggota->where('is_active', true)->groupBy('kelas_id');

        $sudahAda = Kelas::where('tahun_ajaran_id', $tujuan->id)->pluck('id', 'nama');
        $waliTerpakai = Kelas::where('tahun_ajaran_id', $tujuan->id)->whereNotNull('wali_kelas_id')->pluck('wali_kelas_id')->flip();

        $barisKelas = [];
        $kelasBaru = [];      // nama => spesifikasi
        $penugasan = [];      // siswa_id => nama kelas tujuan
        $lulus = [];          // siswa_id
        $semuaAktif = [];

        $tambahKelas = function (string $nama, Kelas $dariKelas, string $tingkat, string $alasan, ?int $waliId) use (&$kelasBaru, $sudahAda) {
            if (isset($kelasBaru[$nama])) {
                return;
            }

            $kelasBaru[$nama] = [
                'nama' => $nama,
                'tingkat' => $tingkat,
                'jurusan_id' => $dariKelas->jurusan_id,
                'wali_kelas_id' => $waliId,
                'alasan' => $alasan,
                'sudah_ada' => $sudahAda->has($nama),
            ];
        };

        foreach ($kelasAsal as $k) {
            $siswaAktif = $aktifPerKelas->get($k->id, collect())->pluck('siswa_id')->map(fn ($i) => (int) $i);
            $semuaAktif = array_merge($semuaAktif, $siswaAktif->all());

            $yangTinggal = $siswaAktif->filter(fn ($i) => isset($tinggal[$i]))->values();
            $yangPindah = $siswaAktif->reject(fn ($i) => isset($tinggal[$i]))->values();

            $aksi = 'naik';
            $namaTujuan = null;

            if (strtoupper($k->tingkat) === 'XII') {
                $aksi = 'lulus';
                foreach ($yangPindah as $id) {
                    $lulus[] = $id;
                }
            } else {
                $namaTujuan = self::namaTujuan($k->nama, $k->tingkat);

                if ($namaTujuan === null) {
                    $aksi = 'tak_dikenal';
                    $masalah[] = "Kelas \"{$k->nama}\" tidak bisa dinaikkan otomatis (nama tidak memuat tingkat {$k->tingkat}). Ubah namanya dulu.";
                } else {
                    $waliId = null;

                    if (! empty($opsi['bawa_wali']) && $k->wali_kelas_id && ! $waliTerpakai->has($k->wali_kelas_id)) {
                        $waliId = (int) $k->wali_kelas_id;
                        $waliTerpakai->put($waliId, 0);
                    }

                    if ($yangPindah->isNotEmpty()) {
                        $tambahKelas($namaTujuan, $k, self::NAIK[strtoupper($k->tingkat)], 'naik', $waliId);
                    }

                    foreach ($yangPindah as $id) {
                        $penugasan[$id] = $namaTujuan;
                    }
                }
            }

            // Siswa tinggal kelas tetap di tingkat yang sama, pada kelas bernama sama di tahun ajaran baru
            if ($yangTinggal->isNotEmpty() && $aksi !== 'tak_dikenal') {
                $tambahKelas($k->nama, $k, strtoupper($k->tingkat), 'tinggal', null);

                foreach ($yangTinggal as $id) {
                    $penugasan[$id] = $k->nama;
                }
            }

            // Kelas X baru yang masih kosong, siap diisi siswa baru lewat import CSV
            if (! empty($opsi['buat_kelas_x']) && strtoupper($k->tingkat) === 'X') {
                $tambahKelas($k->nama, $k, 'X', 'x_baru', null);
            }

            $barisKelas[] = [
                'id' => $k->id,
                'nama' => $k->nama,
                'tingkat' => $k->tingkat,
                'siswa' => $siswaAktif->count(),
                'naik' => $aksi === 'naik' ? $yangPindah->count() : 0,
                'lulus' => $aksi === 'lulus' ? $yangPindah->count() : 0,
                'tinggal' => $aksi === 'tak_dikenal' ? 0 : $yangTinggal->count(),
                'aksi' => $aksi,
                'tujuan' => $aksi === 'lulus' ? null : $namaTujuan,
            ];
        }

        // Kenaikan yang sudah pernah dijalankan: siswa sudah punya kelas di tahun ajaran tujuan
        $sudahDinaikkan = $penugasan === [] ? 0 : (int) KelasSiswa::where('tahun_ajaran_id', $tujuan->id)
            ->whereIn('siswa_id', array_keys($penugasan))
            ->count();

        if ($sudahDinaikkan > 0) {
            $masalah[] = "{$sudahDinaikkan} siswa sudah punya kelas di tahun ajaran tujuan. Kenaikan kelas kemungkinan sudah pernah dijalankan.";
        }

        if ($penugasan === [] && $lulus === [] && $masalah === []) {
            $masalah[] = 'Tidak ada siswa aktif yang bisa diproses.';
        }

        $naik = count(array_filter($penugasan, fn ($nama, $id) => ! isset($tinggal[$id]), ARRAY_FILTER_USE_BOTH));

        return [
            'valid' => $masalah === [],
            'masalah' => $masalah,
            'kelas' => $barisKelas,
            'kelas_baru' => array_values($kelasBaru),
            'total' => [
                'siswa_aktif' => count($semuaAktif),
                'naik' => $naik,
                'tinggal' => count(array_filter($penugasan, fn ($nama, $id) => isset($tinggal[$id]), ARRAY_FILTER_USE_BOTH)),
                'lulus' => count($lulus),
                'dilewati' => $dilewati,
                'kelas_dibuat' => count(array_filter($kelasBaru, fn ($s) => ! $s['sudah_ada'])),
                'kelas_sudah_ada' => count(array_filter($kelasBaru, fn ($s) => $s['sudah_ada'])),
            ],
            // Dipakai jalankan(); tidak ditampilkan
            '_penugasan' => $penugasan,
            '_lulus' => $lulus,
        ];
    }

    /**
     * @return array{kelas_dibuat: int, naik: int, tinggal: int, lulus: int, diaktifkan: bool}
     */
    public function jalankan(TahunAjaran $asal, TahunAjaran $tujuan, array $opsi = []): array
    {
        $r = $this->rencana($asal, $tujuan, $opsi);

        if (! $r['valid']) {
            throw new RuntimeException(implode(' ', $r['masalah']));
        }

        $tanggalLulus = $opsi['tanggal_lulus'] ?? $asal->tanggal_selesai->toDateString();

        DB::transaction(function () use ($r, $asal, $tujuan, $opsi, $tanggalLulus) {
            // 1. Kelas tahun ajaran baru
            $idKelas = Kelas::where('tahun_ajaran_id', $tujuan->id)->pluck('id', 'nama')->all();

            foreach ($r['kelas_baru'] as $spek) {
                if (isset($idKelas[$spek['nama']])) {
                    continue;
                }

                $idKelas[$spek['nama']] = Kelas::create([
                    'tahun_ajaran_id' => $tujuan->id,
                    'nama' => $spek['nama'],
                    'tingkat' => $spek['tingkat'],
                    'jurusan_id' => $spek['jurusan_id'],
                    'wali_kelas_id' => $spek['wali_kelas_id'],
                ])->id;
            }

            // 2. Penempatan siswa di kelas baru
            $sekarang = now();
            $baris = [];

            foreach ($r['_penugasan'] as $siswaId => $namaKelas) {
                $baris[] = [
                    'tahun_ajaran_id' => $tujuan->id,
                    'kelas_id' => $idKelas[$namaKelas],
                    'siswa_id' => $siswaId,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ];
            }

            foreach (array_chunk($baris, 500) as $bagian) {
                KelasSiswa::upsert($bagian, ['tahun_ajaran_id', 'siswa_id'], ['kelas_id', 'updated_at']);
            }

            // 3. Kelas XII lulus. Pembaruan massal tidak memicu hook model, jadi is_active diisi langsung.
            foreach (array_chunk($r['_lulus'], 500) as $bagian) {
                Siswa::whereIn('id', $bagian)->update([
                    'status' => Siswa::STATUS_LULUS,
                    'is_active' => false,
                    'tanggal_keluar' => $tanggalLulus,
                    'keterangan_keluar' => 'Lulus tahun ajaran ' . $asal->nama,
                ]);
            }

            // 4. Tahun ajaran tujuan menjadi aktif (yang lain otomatis nonaktif lewat hook model)
            if (! empty($opsi['aktifkan'])) {
                $tujuan->update(['is_active' => true]);
            }
        });

        return [
            'kelas_dibuat' => $r['total']['kelas_dibuat'],
            'naik' => $r['total']['naik'],
            'tinggal' => $r['total']['tinggal'],
            'lulus' => $r['total']['lulus'],
            'diaktifkan' => ! empty($opsi['aktifkan']),
        ];
    }
}