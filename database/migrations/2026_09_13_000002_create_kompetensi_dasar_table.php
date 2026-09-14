<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kompetensi_dasar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->string('kode_kompetensi', 50);
            $table->text('deskripsi');
            $table->string('materi_pokok', 255)->nullable();
            $table->enum('level_kognitif', ['pengetahuan', 'pemahaman', 'penerapan', 'penalaran']);
            $table->text('batasan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['mapel_id', 'kode_kompetensi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kompetensi_dasar');
    }
};
