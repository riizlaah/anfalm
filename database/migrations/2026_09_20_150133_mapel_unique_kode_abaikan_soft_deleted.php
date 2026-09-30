<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapel', function (Blueprint $table) {
            $table->string('kode_unik', 20)
                ->storedAs('IF(`deleted_at` IS NULL, `kode`, NULL)')
                ->after('kode');
            $table->unique('kode_unik', 'mapel_kode_unik_unique');
            $table->dropUnique(['kode']);
        });
    }

    public function down(): void
    {
        Schema::table('mapel', function (Blueprint $table) {
            $table->unique('kode');
            $table->dropUnique(['kode_unik']);
            $table->dropColumn('kode_unik');
        });
    }
};
