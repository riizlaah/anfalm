<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paket_tryout', function (Blueprint $table) {
            $table->id();
            $table->string('nama_paket', 255);
            $table->text('deskripsi')->nullable();
            $table->enum('tingkat', ['SD', 'SMP', 'SMA', 'SMK'])->default('SMK');
            $table->integer('batas_waktu_menit');

            $table->foreignId('mapel_wajib_1')->nullable()->constrained('mapel')->nullOnDelete();
            $table->foreignId('mapel_wajib_2')->nullable()->constrained('mapel')->nullOnDelete();
            $table->foreignId('mapel_wajib_3')->nullable()->constrained('mapel')->nullOnDelete();
            $table->foreignId('mapel_pilihan_1')->nullable()->constrained('mapel')->nullOnDelete();
            $table->foreignId('mapel_pilihan_2')->nullable()->constrained('mapel')->nullOnDelete();

            $table->foreignId('paket_soal_wajib_1_id')->nullable()->constrained('paket_soal')->nullOnDelete();
            $table->foreignId('paket_soal_wajib_2_id')->nullable()->constrained('paket_soal')->nullOnDelete();
            $table->foreignId('paket_soal_wajib_3_id')->nullable()->constrained('paket_soal')->nullOnDelete();
            $table->foreignId('paket_soal_pilihan_1_id')->nullable()->constrained('paket_soal')->nullOnDelete();
            $table->foreignId('paket_soal_pilihan_2_id')->nullable()->constrained('paket_soal')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_tryout');
    }
};