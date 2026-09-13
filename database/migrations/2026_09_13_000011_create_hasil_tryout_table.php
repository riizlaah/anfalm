<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_tryout', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paket_tryout_id')->constrained('paket_tryout')->cascadeOnDelete();
            $table->decimal('theta_final', 5, 3);
            $table->decimal('standard_error', 5, 3)->nullable();
            $table->decimal('skor_irt_total', 5, 3);
            $table->integer('skor_konversi')->nullable();
            $table->integer('jumlah_benar')->nullable();
            $table->integer('jumlah_salah')->nullable();
            $table->integer('total_soal')->nullable();
            $table->integer('durasi_total')->nullable();
            $table->timestamp('selesai_pada');
            $table->timestamps();

            $table->unique(['user_id', 'paket_tryout_id']);
            $table->index('user_id');
            $table->index('paket_tryout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_tryout');
    }
};