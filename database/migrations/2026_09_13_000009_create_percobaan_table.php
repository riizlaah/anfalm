<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('percobaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('jenis', ['tryout', 'latihan']);
            $table->foreignId('paket_tryout_id')->nullable()->constrained('paket_tryout')->nullOnDelete();
            $table->foreignId('mapel_id')->nullable()->constrained('mapel')->nullOnDelete();
            $table->integer('jumlah_soal')->nullable();
            $table->integer('batas_waktu_menit')->nullable();
            $table->enum('status', ['berjalan', 'selesai', 'dibatalkan'])->default('berjalan');
            $table->json('daftar_soal')->nullable();
            $table->integer('posisi_soal')->default(0);
            $table->integer('urutan_mapel')->default(0);
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->integer('durasi_detik')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index('paket_tryout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('percobaan');
    }
};
