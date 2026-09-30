<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NILAI_LAMA = [
        'pengetahuan',
        'pemahaman',
        'penerapan',
        'penalaran',
    ];

    private const NILAI_GABUNGAN = 'pengetahuan_dan_pemahaman';

    private const NILAI_BARU = [
        'pengetahuan_dan_pemahaman',
        'penerapan',
        'penalaran',
    ];

    /**
     * TKA hanya memakai tiga level kompetensi, jadi `pengetahuan` dan `pemahaman`
     * digabung menjadi `pengetahuan_dan_pemahaman`. MySQL menolak enum yang
     * langsung menyempit ke nilai yang belum ada, karena itu kolom dilebarkan
     * dulu, datanya dimigrasikan, lalu disempitkan kembali.
     */
    public function up(): void
    {
        $this->ubahEnum([...self::NILAI_LAMA, self::NILAI_GABUNGAN]);

        DB::table('kompetensi_dasar')
            ->whereIn('level_kognitif', ['pengetahuan', 'pemahaman'])
            ->update(['level_kognitif' => self::NILAI_GABUNGAN]);

        $this->ubahEnum(self::NILAI_BARU);
    }

    /**
     * Lossy: asal-usul `pengetahuan` dan `pemahaman` per baris sudah hilang
     * setelah digabung, sehingga semua baris gabungan dikembalikan ke
     * `pengetahuan` agar skema persis seperti semula.
     */
    public function down(): void
    {
        $this->ubahEnum([...self::NILAI_LAMA, self::NILAI_GABUNGAN]);

        DB::table('kompetensi_dasar')
            ->where('level_kognitif', self::NILAI_GABUNGAN)
            ->update(['level_kognitif' => 'pengetahuan']);

        $this->ubahEnum(self::NILAI_LAMA);
    }

    /**
     * @param  array<int, string>  $nilai
     */
    private function ubahEnum(array $nilai): void
    {
        Schema::table('kompetensi_dasar', function (Blueprint $table) use ($nilai) {
            $table->enum('level_kognitif', $nilai)->change();
        });
    }
};
