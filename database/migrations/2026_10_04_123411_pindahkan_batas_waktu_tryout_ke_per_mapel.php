<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batas waktu pindah dari paket tryout ke tiap baris mapelnya (2b).
     *
     * Satu angka untuk seluruh tryout tidak bisa lagi melayani pembedaan
     * wajib/pilihan, jadi `paket_tryout.batas_waktu_menit` dihapus dan
     * diganti `paket_tryout_mapel.menit` — nilai bawaannya wajib 75 menit,
     * pilihan 60 menit.
     */
    public function up(): void
    {
        Schema::table('paket_tryout_mapel', function (Blueprint $table): void {
            $table->unsignedSmallInteger('menit')->default(60)->after('paket_soal_id');
        });

        // Baris yang sudah ada kebagian default pilihan; wajib dinaikkan
        // menyusul supaya paket lama tidak tiba-tiba berdurasi 60 menit.
        $mapelWajib = DB::table('mapel')
            ->where('jenis', 'wajib')
            ->whereNull('deleted_at')
            ->pluck('id');

        $mapelPilihan = DB::table('mapel')
            ->where('jenis', '!=', 'wajib')
            ->whereNull('deleted_at')
            ->pluck('id');

        DB::table('paket_tryout_mapel')->whereIn('mapel_id', $mapelWajib)->update(['menit' => 75]);
        DB::table('paket_tryout_mapel')->whereIn('mapel_id', $mapelPilihan)->update(['menit' => 60]);

        Schema::table('paket_tryout', function (Blueprint $table): void {
            $table->dropColumn('batas_waktu_menit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paket_tryout', function (Blueprint $table): void {
            $table->integer('batas_waktu_menit')->default(120)->after('tingkat');
        });

        DB::table('paket_tryout')->update(['batas_waktu_menit' => 120]);

        Schema::table('paket_tryout_mapel', function (Blueprint $table): void {
            $table->dropColumn('menit');
        });
    }
};
