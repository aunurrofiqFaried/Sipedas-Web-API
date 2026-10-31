<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('pengumuman', function (Blueprint $table) {
        $table->id();
        $table->string('judul', 150);
        $table->text('isi');
        $table->enum('tingkat', ['biasa', 'penting'])->default('biasa');
        $table->date('berlaku_sampai')->nullable(); // kosong = berlaku terus selama aktif
        $table->boolean('is_aktif')->default(true);
        $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index(['is_aktif', 'id']);
    });

    // Pengumuman terakhir yang sudah dibaca tiap akun
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedBigInteger('pengumuman_dibaca_id')->default(0)->after('is_active');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('pengumuman_dibaca_id');
    });

    Schema::dropIfExists('pengumuman');
}
};
