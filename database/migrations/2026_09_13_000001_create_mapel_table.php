<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapel', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->enum('tingkat', ['SD', 'SMP', 'SMA', 'SMK', 'all'])->default('all');
            $table->enum('jenis', ['wajib', 'pilihan_umum', 'pilihan_kejuruan']);
            $table->boolean('is_pkk')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapel');
    }
};
