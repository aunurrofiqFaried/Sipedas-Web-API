<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            // aktif, lulus, pindah, keluar
            $table->string('status', 10)->default('aktif')->after('is_active');
            $table->date('tanggal_keluar')->nullable()->after('status');
            $table->string('keterangan_keluar')->nullable()->after('tanggal_keluar');

            $table->index('status');
        });

        // Siswa yang sudah nonaktif dianggap "keluar"; ubah manual bila sebenarnya lulus atau pindah
        DB::table('siswa')->where('is_active', false)->update(['status' => 'keluar']);
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'tanggal_keluar', 'keterangan_keluar']);
        });
    }
};