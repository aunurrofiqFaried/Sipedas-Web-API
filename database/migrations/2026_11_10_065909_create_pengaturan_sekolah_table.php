<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengaturan_sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('instansi_1')->nullable(); // PEMERINTAH PROVINSI JAWA TIMUR
            $table->string('instansi_2')->nullable(); // DINAS PENDIDIKAN
            $table->string('nama_sekolah');
            $table->string('alamat')->nullable();
            $table->string('telepon', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('kota', 80)->default('Magetan'); // untuk tanggal tanda tangan
            $table->string('kop_gambar')->nullable();       // berkas gambar kop (opsional)
            $table->timestamps();
        });

        // Satu baris identitas, diisi dari kop sekolah
        DB::table('pengaturan_sekolah')->insert([
            'instansi_1' => 'PEMERINTAH PROVINSI JAWA TIMUR',
            'instansi_2' => 'DINAS PENDIDIKAN',
            'nama_sekolah' => 'SMK NEGERI TAKERAN',
            'alamat' => 'Ds. Kuwonharjo Kec. Takeran Kab. Magetan 63383',
            'telepon' => '( 0351 ) 439094',
            'website' => 'www.smkn1-takeran.sch.id',
            'email' => 'smkn1takeran@gmail.com',
            'kota' => 'Magetan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_sekolah');
    }
};
