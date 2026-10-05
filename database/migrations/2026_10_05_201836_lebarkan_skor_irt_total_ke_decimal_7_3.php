<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `skor_irt_total` adalah jumlah proporsi jawaban benar — maksimumnya sama
 * dengan jumlah soal terjawab. `decimal(5,3)` hanya muat sampai 99.999, sedangkan
 * paket produksi berisi 135 soal, sehingga peserta yang mengerjakan paket besar
 * selalu berakhir di PDOException 22003 tepat saat submit.
 *
 * Tujuh digit di depan koma menampung sampai 9999.999, jauh di atas jumlah soal
 * yang masuk akal, tanpa mengubah satu nilai pun yang sudah tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_tryout', function (Blueprint $table) {
            $table->decimal('skor_irt_total', 7, 3)->change();
        });
    }

    public function down(): void
    {
        // Mengembalikan ke `decimal(5,3)` akan menolak setiap baris yang nilainya
        // di atas 99.999. Jalankan hanya setelah nilai seperti itu dihapus atau
        // dikembalikan ke rentang lama.
        Schema::table('hasil_tryout', function (Blueprint $table) {
            $table->decimal('skor_irt_total', 5, 3)->change();
        });
    }
};
