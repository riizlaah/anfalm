<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel pivot pilihan mapel peserta (butir 96). Pasangan (user, mapel)
     * cukup muncul sekali, jadi kunci utamanya gabungan — bukan `id` sendiri
     * yang hanya memindahkan masalah duplikat ke pemeriksaan di aplikasi.
     * Tidak ada `timestamps`: baris ini murni relasi, tidak pernah dibaca
     * kapan dibuat maupun diubah.
     */
    public function up(): void
    {
        Schema::create('mapel_pilihan_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->primary(['user_id', 'mapel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mapel_pilihan_user');
    }
};
