<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MIN_PERNYATAAN = 3;

    private const TEKS_PLACEHOLDER = '[Perlu dilengkapi]';

    /**
     * Minimal pernyataan PG Kategori naik dari 2 menjadi 3. Soal lama yang masih
     * punya 2 pernyataan belum otomatis sah, jadi baris placeholder ditambahkan
     * supaya tetap bisa disimpan dan diedit; teksnya ditandai agar mudah dicari.
     */
    public function up(): void
    {
        $jumlah = DB::table('pernyataan_kategori')
            ->whereIn('soal_id', DB::table('soal')->where('tipe_soal', 'pg_kategori')->pluck('id'))
            ->groupBy('soal_id')
            ->selectRaw('soal_id, COUNT(*) AS total')
            ->havingRaw('COUNT(*) < ?', [self::MIN_PERNYATAAN])
            ->pluck('total', 'soal_id');

        $waktu = now();

        foreach ($jumlah as $soalId => $total) {
            for ($urutan = $total + 1; $urutan <= self::MIN_PERNYATAAN; $urutan++) {
                DB::table('pernyataan_kategori')->insert([
                    'soal_id' => $soalId,
                    'teks_pernyataan' => self::TEKS_PLACEHOLDER.' '.$urutan,
                    'kategori_benar' => null,
                    'a_diskriminasi' => null,
                    'b_kesulitan' => null,
                    'c_tebakan' => null,
                    'urutan' => $urutan,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            }
        }
    }

    /**
     * Placeholder dicabut kembali, begitu juga jawaban benar yang sebelumnya
     * tertaut ke baris placeholder.
     */
    public function down(): void
    {
        $placeholder = DB::table('pernyataan_kategori')
            ->where('teks_pernyataan', 'like', self::TEKS_PLACEHOLDER.'%')
            ->orderBy('soal_id')
            ->orderByDesc('urutan')
            ->get()
            ->groupBy('soal_id');

        foreach ($placeholder as $rows) {
            $kategoriCadangan = DB::table('pernyataan_kategori')
                ->where('soal_id', $rows->first()->soal_id)
                ->whereNotIn('id', $rows->pluck('id'))
                ->whereNotNull('kategori_benar')
                ->value('kategori_benar');

            if ($kategoriCadangan !== null) {
                DB::table('pernyataan_kategori')
                    ->whereIn('id', $rows->pluck('id'))
                    ->update(['kategori_benar' => $kategoriCadangan]);
            }

            DB::table('pernyataan_kategori')->whereIn('id', $rows->pluck('id'))->delete();
        }
    }
};
