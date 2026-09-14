<?php

namespace Database\Factories;

use App\Models\KompetensiDasar;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Factories\Factory;

class KompetensiDasarFactory extends Factory
{
    protected $model = KompetensiDasar::class;

    protected static int $counter = 0;

    public function definition(): array
    {
        static::$counter++;

        return [
            'mapel_id' => Mapel::factory(),
            'kode_kompetensi' => '3.'.static::$counter,
            'deskripsi' => fake()->sentence(),
            'materi_pokok' => fake()->words(3, true),
            'level_kognitif' => 'pemahaman',
            'batasan' => null,
        ];
    }
}
