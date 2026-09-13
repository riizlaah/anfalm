<?php

namespace Database\Factories;

use App\Models\KompetensiDasar;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoalFactory extends Factory
{
    protected $model = Soal::class;

    protected static int $counter = 0;

    public function definition(): array
    {
        static::$counter++;

        return [
            'kompetensi_dasar_id' => KompetensiDasar::factory(),
            'tipe_soal' => Soal::TIPE_PG,
            'pertanyaan' => 'Pertanyaan nomor ' . static::$counter . ' adalah ...',
            'gambar_url' => null,
            'pembahasan' => 'Pembahasan soal nomor ' . static::$counter,
            'daftar_kategori' => null,
            'a_diskriminasi' => 1.0,
            'b_kesulitan' => 0.0,
            'c_tebakan' => 0.25,
            'created_by' => null,
        ];
    }

    public function pgKompleks(): static
    {
        return $this->state(['tipe_soal' => Soal::TIPE_PG_KOMPLEKS]);
    }

    public function pgKategori(array $kategoriList = null): static
    {
        return $this->state([
            'tipe_soal' => Soal::TIPE_PG_KATEGORI,
            'daftar_kategori' => $kategoriList ?? ['Benar', 'Salah'],
        ]);
    }
}