<?php

namespace Database\Factories;

use App\Models\OpsiJawaban;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpsiJawabanFactory extends Factory
{
    protected $model = OpsiJawaban::class;

    public function definition(): array
    {
        return [
            'soal_id' => Soal::factory(),
            'teks_opsi' => fake()->unique()->words(4, true),
            'is_benar' => false,
            'urutan' => 1,
            'a_diskriminasi' => null,
            'b_kesulitan' => null,
            'c_tebakan' => null,
        ];
    }

    public function benar(): static
    {
        return $this->state(['is_benar' => true]);
    }
}
