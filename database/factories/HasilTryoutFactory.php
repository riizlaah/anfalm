<?php

namespace Database\Factories;

use App\Models\HasilTryout;
use App\Models\PaketTryout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HasilTryoutFactory extends Factory
{
    protected $model = HasilTryout::class;

    protected static int $counter = 0;

    public function definition(): array
    {
        static::$counter++;

        return [
            'user_id' => User::factory(),
            'paket_tryout_id' => PaketTryout::factory(),
            'theta_final' => fake()->randomFloat(3, -3, 3),
            'standard_error' => fake()->randomFloat(3, 0.1, 1.0),
            'skor_irt_total' => fake()->randomFloat(3, -3, 3),
            'skor_konversi' => fake()->numberBetween(200, 700),
            'jumlah_benar' => 20,
            'jumlah_salah' => 10,
            'total_soal' => 30,
            'durasi_total' => fake()->numberBetween(3000, 7200),
            'selesai_pada' => now()->subMinutes(static::$counter),
        ];
    }
}
