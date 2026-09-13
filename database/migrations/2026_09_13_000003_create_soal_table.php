<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kompetensi_dasar_id')->constrained('kompetensi_dasar')->cascadeOnDelete();
            $table->enum('tipe_soal', ['pg', 'pg_kompleks', 'pg_kategori']);
            $table->text('pertanyaan');
            $table->string('gambar_url', 255)->nullable();
            $table->text('pembahasan')->nullable();
            $table->json('daftar_kategori')->nullable();
            $table->decimal('a_diskriminasi', 5, 3)->default(1.0);
            $table->decimal('b_kesulitan', 5, 3)->default(0.0);
            $table->decimal('c_tebakan', 5, 3)->default(0.25);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('kompetensi_dasar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal');
    }
};