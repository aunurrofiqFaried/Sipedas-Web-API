<?php

namespace App\Support;

use App\Models\Jadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KepatuhanJurnal
{
    public const BATAS_BAIK = 90;
    public const BATAS_CUKUP = 75;

    // Kepatuhan semua guru pada periode ini; kalau $guruId diisi, hanya guru itu beserta daftar sesi terlewat
    public static function hitung(Carbon $dari, Carbon $sampai, ?int $guruId = null): array
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        // Dihitung sampai kemarin (jurnal hari ini masih bisa diisi) dan tidak melewati tahun ajaran
        $awal = $dari->copy()->startOfDay()->max($ta->tanggal_mulai->copy()->startOfDay());
        $akhir = $sampai->copy()->startOfDay()
            ->min(today()->subDay())
            ->min($ta->tanggal_selesai->copy()->startOfDay());

        $persen = fn (int $terisi, int $seharusnya) => $seharusnya > 0 ? (int) round($terisi / $seharusnya * 100) : null;

        $statusDari = fn (?int $p) => $p === null ? null : ($p >= self::BATAS_BAIK ? 'baik' : ($p >= self::BATAS_CUKUP ? 'cukup' : 'kurang'));

        // Libur kalender akademik pada rentang ini
        $liburRentang = DB::table('kalender_akademik')
            ->where('jenis', 'libur')
            ->where('tanggal_mulai', '<=', $akhir->toDateString())
            ->where('tanggal_selesai', '>=', $awal->toDateString())
            ->get(['tanggal_mulai', 'tanggal_selesai']);

        $libur = fn (string $t) => $liburRentang->contains(
            fn ($l) => substr((string) $l->tanggal_mulai, 0, 10) <= $t && substr((string) $l->tanggal_selesai, 0, 10) >= $t
        );

        // Hari efektif: Senin sampai Jumat yang bukan libur
        $tanggalPerHari = []; // hari ISO => [tanggal, ...]
        $valid = [];          // tanggal => hari ISO

        if ($awal->lte($akhir)) {
            foreach (CarbonPeriod::create($awal, $akhir) as $d) {
                if ($d->isoWeekday() <= 5 && ! $libur($d->toDateString())) {
                    $valid[$d->toDateString()] = $d->isoWeekday();
                    $tanggalPerHari[$d->isoWeekday()][] = $d->toDateString();
                }
            }
        }

        $jadwal = Jadwal::query()
            ->whereHas('kelas', fn ($q) => $q->where('tahun_ajaran_id', $ta->id))
            ->when($guruId, fn ($q) => $q->where('guru_id', $guruId))
            ->with(['kelas:id,nama', 'mapel:id,nama'])
            ->get();

        $guru = User::whereIn('id', $jadwal->pluck('guru_id')->unique())
            ->where('is_active', true)
            ->get(['id', 'name', 'nip'])
            ->keyBy('id');

        $jadwal = $jadwal->filter(fn ($j) => $guru->has($j->guru_id))->values();

        // Jurnal yang sudah terisi: kunci "jadwal|tanggal"
        $sudah = [];

        DB::table('jurnal')
            ->whereIn('jadwal_id', $jadwal->pluck('id'))
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['jadwal_id', 'tanggal'])
            ->each(function ($r) use (&$sudah, $valid) {
                $t = substr((string) $r->tanggal, 0, 10);

                if (isset($valid[$t])) {
                    $sudah[$r->jadwal_id . '|' . $t] = true;
                }
            });

        // Terakhir kali tiap guru mengisi jurnal (sepanjang waktu)
        $terakhir = DB::table('jurnal')
            ->whereIn('guru_id', $guru->keys())
            ->groupBy('guru_id')
            ->selectRaw('guru_id, MAX(tanggal) as terakhir')
            ->pluck('terakhir', 'guru_id');

        $perGuru = [];
        $harian = [];
        $terlewat = [];

        foreach ($jadwal as $j) {
            $g = $j->guru_id;
            $perGuru[$g] ??= ['seharusnya' => 0, 'terisi' => 0, 'sesi_per_minggu' => 0];
            $perGuru[$g]['sesi_per_minggu']++;

            foreach ($tanggalPerHari[(int) $j->hari] ?? [] as $t) {
                $perGuru[$g]['seharusnya']++;
                $harian[$t]['seharusnya'] = ($harian[$t]['seharusnya'] ?? 0) + 1;

                if (isset($sudah[$j->id . '|' . $t])) {
                    $perGuru[$g]['terisi']++;
                    $harian[$t]['terisi'] = ($harian[$t]['terisi'] ?? 0) + 1;
                } elseif ($guruId) {
                    $tgl = Carbon::parse($t)->locale('id');

                    $terlewat[] = [
                        'tanggal' => $t,
                        'hari' => $tgl->translatedFormat('l'),
                        'tanggal_label' => $tgl->translatedFormat('j M Y'),
                        'kelas' => $j->kelas?->nama,
                        'mapel' => $j->mapel?->nama,
                        'jam_ke_mulai' => $j->jam_ke_mulai,
                        'jam_ke_selesai' => $j->jam_ke_selesai,
                        'jam_mulai' => substr((string) $j->jam_mulai, 0, 5),
                        'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
                    ];
                }
            }
        }

        $baris = collect($perGuru)
            ->map(function (array $v, int $id) use ($guru, $persen, $statusDari, $terakhir) {
                $p = $persen($v['terisi'], $v['seharusnya']);

                return [
                    'id' => $id,
                    'nama' => $guru[$id]->name,
                    'nip' => $guru[$id]->nip,
                    'sesi_per_minggu' => $v['sesi_per_minggu'],
                    'seharusnya' => $v['seharusnya'],
                    'terisi' => $v['terisi'],
                    'terlewat' => $v['seharusnya'] - $v['terisi'],
                    'persen' => $p,
                    'status' => $statusDari($p),
                    'terakhir_isi' => $terakhir->get($id) ? substr((string) $terakhir->get($id), 0, 10) : null,
                ];
            })
            // Terendah dulu supaya yang perlu ditindaklanjuti ada di atas; yang belum punya data di bawah
            ->sort(fn (array $a, array $b) => [$a['persen'] ?? 999, $a['nama']] <=> [$b['persen'] ?? 999, $b['nama']])
            ->values();

        // Tren mingguan seluruh sekolah
        $mingguan = [];

        foreach ($harian as $t => $v) {
            $senin = Carbon::parse($t)->startOfWeek()->toDateString();
            $mingguan[$senin]['seharusnya'] = ($mingguan[$senin]['seharusnya'] ?? 0) + ($v['seharusnya'] ?? 0);
            $mingguan[$senin]['terisi'] = ($mingguan[$senin]['terisi'] ?? 0) + ($v['terisi'] ?? 0);
        }

        ksort($mingguan);

        $totalSeharusnya = (int) $baris->sum('seharusnya');
        $totalTerisi = (int) $baris->sum('terisi');

        usort($terlewat, fn (array $a, array $b) => [$b['tanggal'], $a['jam_ke_mulai']] <=> [$a['tanggal'], $b['jam_ke_mulai']]);

        return [
            'periode' => [
                'dari' => $dari->toDateString(),
                'sampai' => $sampai->toDateString(),
                'dihitung_sampai' => $akhir->toDateString(),
                'label' => $dari->copy()->locale('id')->translatedFormat('j F Y')
                    . ' s.d. ' . $sampai->copy()->locale('id')->translatedFormat('j F Y'),
            ],
            'ringkasan' => [
                'guru' => $baris->count(),
                'hari_efektif' => count($valid),
                'seharusnya' => $totalSeharusnya,
                'terisi' => $totalTerisi,
                'terlewat' => $totalSeharusnya - $totalTerisi,
                'persen' => $persen($totalTerisi, $totalSeharusnya),
                'baik' => $baris->where('status', 'baik')->count(),
                'cukup' => $baris->where('status', 'cukup')->count(),
                'kurang' => $baris->where('status', 'kurang')->count(),
            ],
            'mingguan' => collect($mingguan)->map(fn (array $v, string $senin) => [
                'label' => Carbon::parse($senin)->locale('id')->translatedFormat('j M'),
                'seharusnya' => $v['seharusnya'],
                'terisi' => $v['terisi'],
                'persen' => $persen($v['terisi'], $v['seharusnya']),
            ])->values()->all(),
            'guru' => $baris->all(),
            'terlewat' => $terlewat,
        ];
    }
}