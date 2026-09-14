<?php

namespace Database\Factories;

use App\Models\Mapel;
use Illuminate\Database\Eloquent\Factories\Factory;

class MapelFactory extends Factory
{
    protected $model = Mapel::class;

    protected static int $counter = 0;

    public function definition(): array
    {
        static::$counter++;

        return [
            'kode' => 'MAP'.str_pad(static::$counter, 3, '0', STR_PAD_LEFT),
            'nama' => fake()->unique()->words(2, true),
            'tingkat' => 'SMK',
            'jenis' => Mapel::JENIS_WAJIB,
            'is_pkk' => false,
        ];
    }

    public function wajib(): static
    {
        return $this->state(['jenis' => Mapel::JENIS_WAJIB]);
    }

    public function pilihanUmum(): static
    {
        return $this->state(['jenis' => Mapel::JENIS_PILIHAN_UMUM]);
    }

    public function pilihanKejuruan(): static
    {
        return $this->state(['jenis' => Mapel::JENIS_PILIHAN_KEJURUAN]);
    }

    public function pkk(): static
    {
        return $this->state(['jenis' => Mapel::JENIS_PILIHAN_KEJURUAN, 'is_pkk' => true]);
    }
}
