<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pengaturan_sekolah', function (Blueprint $table) {
            $table->unsignedTinyInteger('kop_lebar')->default(70)->after('kop_gambar');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_sekolah', function (Blueprint $table) {
            $table->dropColumn('kop_lebar');
        });
    }
};
