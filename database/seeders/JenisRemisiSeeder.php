<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisRemisiSeeder extends Seeder
{
    public function run(): void
    {
        // CONTOH: sesuaikan nama dan poin dengan kebijakan sekolah lewat panel admin
        $data = [
            ['Kerja bakti atau piket kebersihan', 5],
            ['Menjadi petugas upacara', 3],
            ['Mewakili sekolah dalam lomba', 10],
            ['Tertib penuh selama satu minggu', 3],
            ['Kegiatan sosial atau keagamaan', 5],
        ];

        foreach ($data as [$nama, $poin]) {
            DB::table('jenis_remisi')->updateOrInsert(
                ['nama' => $nama],
                ['poin' => $poin, 'is_aktif' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}