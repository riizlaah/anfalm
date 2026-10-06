<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Peserta perlu jeda antar mapel, dan hitung mundur mapel berikutnya baru
     * boleh berjalan setelah ia menekan Mulai. Tanpa penanda tersendiri,
     * `lanjut()` mereset `mulai_mapel` pada detik submit — jadi istirahat
     * justru memakan jatah waktu pengerjaan.
     *
     * Kolom ini berarti "mapel N sudah terkunci, mapel N+1 belum dimulai".
     * Selama ia terisi, `kadaluarsa()` menganggap percobaan tidak bisa
     * kedaluwarsa; tanpa itu, peserta yang berlama-lama di halaman jeda akan
     * dianggap kehabisan waktu pada mapel yang belum pernah dibuka, dan
     * mapel itu terlewat begitu saja.
     *
     * Jeda tidak dibatasi waktunya. Aplikasi tidak punya scheduler, jadi
     * kedaluwarsa selalu dihitung dari request — memaksakan jeda berakhir
     * sendiri hanya akan mengejutkan peserta saat ia kembali membuka halaman.
     */
    public function up(): void
    {
        Schema::table('percobaan', function (Blueprint $table): void {
            $table->timestamp('jeda_mulai')->nullable()->after('mulai_mapel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('percobaan', function (Blueprint $table): void {
            $table->dropColumn('jeda_mulai');
        });
    }
};
