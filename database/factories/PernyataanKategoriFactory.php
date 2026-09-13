<?php

namespace Database\Factories;

use App\Models\PernyataanKategori;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

class PernyataanKategoriFactory extends Factory
{
    protected $model = PernyataanKategori::class;

    public function definition(): array
    {
        return [
            'soal_id' => Soal::factory(),
            'teks_pernyataan' => fake()->sentence(),
            'kategori_benar' => 'Benar',
            'a_diskriminasi' => null,
            'b_kesulitan' => null,
            'c_tebakan' => null,
            'urutan' => 1,
        ];
    }
}