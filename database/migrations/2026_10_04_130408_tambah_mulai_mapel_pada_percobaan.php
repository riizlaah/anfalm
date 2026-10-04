<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hitung mundur tryout kini menempel per mapel, bukan per percobaan (2b),
     * jadi awal mula mapel yang sedang dibuka dicatat terpisah dari
     * `waktu_mulai` — kolom itu tetap menjadi saat percobaan dimulai, sumber
     * durasi total dan kalender streak.
     *
     * Baris lama disalin dari `waktu_mulai` supaya percobaan yang sedang
     * berjalan tidak kehilangan hitung mundurnya.
     */
    public function up(): void
    {
        Schema::table('percobaan', function (Blueprint $table): void {
            $table->timestamp('mulai_mapel')->nullable()->after('waktu_mulai');
        });

        DB::table('percobaan')
            ->whereNotNull('waktu_mulai')
            ->update(['mulai_mapel' => DB::raw('waktu_mulai')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('percobaan', function (Blueprint $table): void {
            $table->dropColumn('mulai_mapel');
        });
    }
};
