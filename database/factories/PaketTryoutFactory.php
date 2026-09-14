<?php

namespace Database\Factories;

use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaketTryoutFactory extends Factory
{
    protected $model = PaketTryout::class;

    protected static string $namaCounter = 'A';

    public function definition(): array
    {
        $nama = 'Tryout '.static::$namaCounter;
        static::$namaCounter++;

        return [
            'nama_paket' => $nama.' - TKA SMK',
            'deskripsi' => fake()->sentence(),
            'tingkat' => 'SMK',
            'batas_waktu_menit' => 120,
            'mapel_wajib_1' => Mapel::factory()->wajib(),
            'mapel_wajib_2' => Mapel::factory()->wajib(),
            'mapel_wajib_3' => Mapel::factory()->wajib(),
            'mapel_pilihan_1' => Mapel::factory()->pkk(),
            'mapel_pilihan_2' => Mapel::factory()->pilihanUmum(),
            'paket_soal_wajib_1_id' => PaketSoal::factory(),
            'paket_soal_wajib_2_id' => PaketSoal::factory(),
            'paket_soal_wajib_3_id' => PaketSoal::factory(),
            'paket_soal_pilihan_1_id' => PaketSoal::factory(),
            'paket_soal_pilihan_2_id' => PaketSoal::factory(),
            'created_by' => null,
        ];
    }
}
