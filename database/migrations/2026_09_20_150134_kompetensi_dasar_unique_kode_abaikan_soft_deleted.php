<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kompetensi_dasar', function (Blueprint $table) {
            $table->string('kode_unik', 50)
                ->storedAs('IF(`deleted_at` IS NULL, `kode_kompetensi`, NULL)')
                ->after('kode_kompetensi');
            $table->unique(['mapel_id', 'kode_unik'], 'kompetensi_dasar_mapel_id_kode_unik_unique');
            $table->dropUnique(['mapel_id', 'kode_kompetensi']);
        });
    }

    public function down(): void
    {
        Schema::table('kompetensi_dasar', function (Blueprint $table) {
            $table->unique(['mapel_id', 'kode_kompetensi']);
            $table->dropUnique(['mapel_id', 'kode_unik']);
            $table->dropColumn('kode_unik');
        });
    }
};
