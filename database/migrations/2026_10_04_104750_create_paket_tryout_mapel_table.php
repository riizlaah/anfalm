<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi paket tryout dipindahkan dari lima pasang kolom tetap menjadi satu
     * baris per mapel, supaya jumlahnya mengikuti katalog mapel — yang bisa
     * bertambah — dan peserta bisa memilih sendiri mapel pilihan yang ia
     * kerjakan.
     *
     * Kolom lama disalin lebih dulu ke tabel baru pada migrasi ini; kolom
     * lamanya sendiri dibuang pada migrasi terpisah setelah salinan ada.
     */
    public function up(): void
    {
        Schema::create('paket_tryout_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_tryout_id')->constrained('paket_tryout')->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mapel');
            $table->foreignId('paket_soal_id')->constrained('paket_soal');
            $table->timestamps();

            $table->unique(['paket_tryout_id', 'mapel_id']);
        });

        $pasangan = [
            ['mapel' => 'mapel_wajib_1', 'paket' => 'paket_soal_wajib_1_id'],
            ['mapel' => 'mapel_wajib_2', 'paket' => 'paket_soal_wajib_2_id'],
            ['mapel' => 'mapel_wajib_3', 'paket' => 'paket_soal_wajib_3_id'],
            ['mapel' => 'mapel_pilihan_1', 'paket' => 'paket_soal_pilihan_1_id'],
            ['mapel' => 'mapel_pilihan_2', 'paket' => 'paket_soal_pilihan_2_id'],
        ];

        $waktu = now();

        DB::table('paket_tryout')->orderBy('id')->each(function (object $paket) use ($pasangan, $waktu): void {
            foreach ($pasangan as $slot) {
                $mapelId = (int) $paket->{$slot['mapel']};
                $paketId = (int) $paket->{$slot['paket']};

                if ($mapelId === 0 || $paketId === 0) {
                    continue;
                }

                DB::table('paket_tryout_mapel')->updateOrInsert(
                    ['paket_tryout_id' => $paket->id, 'mapel_id' => $mapelId],
                    ['paket_soal_id' => $paketId, 'created_at' => $waktu, 'updated_at' => $waktu],
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_tryout_mapel');
    }
};
