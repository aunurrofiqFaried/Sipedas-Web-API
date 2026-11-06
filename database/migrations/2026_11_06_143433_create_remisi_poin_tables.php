<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('jenis_remisi', function (Blueprint $table) {
        $table->id();
        $table->string('nama', 150);
        $table->unsignedSmallInteger('poin');
        $table->text('keterangan')->nullable();
        $table->boolean('is_aktif')->default(true);
        $table->timestamps();
    });

    Schema::create('remisi_siswa', function (Blueprint $table) {
        $table->id();
        $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
        $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
        $table->foreignId('jenis_remisi_id')->constrained('jenis_remisi');
        // SNAPSHOT poin saat diberikan, supaya perubahan bobot nanti tidak mengubah riwayat
        $table->unsignedSmallInteger('poin');
        $table->date('tanggal');
        $table->text('keterangan')->nullable();
        $table->foreignId('dicatat_oleh')->constrained('users');
        $table->timestamps();

        $table->index(['siswa_id', 'tahun_ajaran_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('remisi_siswa');
    Schema::dropIfExists('jenis_remisi');
}
};
