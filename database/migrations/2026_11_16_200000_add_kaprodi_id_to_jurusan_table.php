<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurusan', function (Blueprint $table) {
            // Satu jurusan satu kaprodi, dan satu orang hanya memimpin satu jurusan
            $table->foreignId('kaprodi_id')->nullable()->after('nama')->unique()
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jurusan', function (Blueprint $table) {
            $table->dropForeign(['kaprodi_id']);
            $table->dropUnique(['kaprodi_id']);
            $table->dropColumn('kaprodi_id');
        });
    }
};