<?php

namespace Database\Factories;

use App\Models\Mapel;
use App\Models\PaketSoal;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaketSoalFactory extends Factory
{
    protected $model = PaketSoal::class;

    public function definition(): array
    {
        return [
            'nama_paket' => fake()->words(4, true).' Paket',
            'deskripsi' => fake()->sentence(),
            'mapel_id' => Mapel::factory(),
            'created_by' => null,
        ];
    }
}
