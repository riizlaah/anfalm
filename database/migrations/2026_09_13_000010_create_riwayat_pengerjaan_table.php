<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pengerjaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('percobaan_id')->constrained('percobaan')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->foreignId('paket_tryout_id')->nullable()->constrained('paket_tryout')->nullOnDelete();
            $table->json('jawaban_user')->nullable();
            $table->boolean('is_benar')->default(false);
            $table->enum('mode', ['tryout', 'latihan']);
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->integer('durasi_detik')->nullable();
            $table->decimal('theta_est_moment', 5, 3)->nullable();
            $table->decimal('skor_irt', 5, 3)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('percobaan_id');
            $table->index('soal_id');
            $table->index('paket_tryout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pengerjaan');
    }
};
