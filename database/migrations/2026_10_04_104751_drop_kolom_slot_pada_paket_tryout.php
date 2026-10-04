<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom slot lama sudah tidak dibaca maupun ditulis aplikasi — isinya
     * sudah tersalin ke `paket_tryout_mapel` pada migrasi sebelumnya.
     *
     * `down()` hanya mengembalikan struktur kolomnya, bukan isinya, karena
     * data lama tidak lagi disimpan di sini; isi yang sudah terlanjur
     * dimodifikasi aplikasi tidak bisa dipulihkan dari satu arah ini.
     */
    public function up(): void
    {
        $kolom = [
            'mapel_wajib_1',
            'mapel_wajib_2',
            'mapel_wajib_3',
            'mapel_pilihan_1',
            'mapel_pilihan_2',
            'paket_soal_wajib_1_id',
            'paket_soal_wajib_2_id',
            'paket_soal_wajib_3_id',
            'paket_soal_pilihan_1_id',
            'paket_soal_pilihan_2_id',
        ];

        // Kolom slot ber-foreign key, jadi keterangannya dilepas lebih dulu —
        // MariaDB menolak penghapusan kolom yang masih dirujuk constraint.
        Schema::table('paket_tryout', function (Blueprint $table) use ($kolom) {
            foreach ($kolom as $kolomSlot) {
                $table->dropForeign([$kolomSlot]);
            }
        });

        Schema::table('paket_tryout', function (Blueprint $table) use ($kolom) {
            $table->dropColumn($kolom);
        });
    }

    public function down(): void
    {
        Schema::table('paket_tryout', function (Blueprint $table) {
            $table->unsignedBigInteger('mapel_wajib_1')->nullable()->after('batas_waktu_menit');
            $table->unsignedBigInteger('mapel_wajib_2')->nullable()->after('mapel_wajib_1');
            $table->unsignedBigInteger('mapel_wajib_3')->nullable()->after('mapel_wajib_2');
            $table->unsignedBigInteger('mapel_pilihan_1')->nullable()->after('mapel_wajib_3');
            $table->unsignedBigInteger('mapel_pilihan_2')->nullable()->after('mapel_pilihan_1');
            $table->unsignedBigInteger('paket_soal_wajib_1_id')->nullable()->after('mapel_pilihan_2');
            $table->unsignedBigInteger('paket_soal_wajib_2_id')->nullable()->after('paket_soal_wajib_1_id');
            $table->unsignedBigInteger('paket_soal_wajib_3_id')->nullable()->after('paket_soal_wajib_2_id');
            $table->unsignedBigInteger('paket_soal_pilihan_1_id')->nullable()->after('paket_soal_wajib_3_id');
            $table->unsignedBigInteger('paket_soal_pilihan_2_id')->nullable()->after('paket_soal_pilihan_1_id');
        });
    }
};
