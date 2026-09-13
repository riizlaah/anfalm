<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pernyataan_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->text('teks_pernyataan');
            $table->string('kategori_benar', 50);
            $table->decimal('a_diskriminasi', 5, 3)->nullable();
            $table->decimal('b_kesulitan', 5, 3)->nullable();
            $table->decimal('c_tebakan', 5, 3)->nullable();
            $table->integer('urutan')->nullable();
            $table->index('soal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pernyataan_kategori');
    }
};