<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->enum('jenis', ['libur', 'peringatan']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai'); // satu hari = sama dengan tanggal_mulai
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};
