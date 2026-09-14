<?php

namespace Database\Factories;

use App\Models\Percobaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PercobaanFactory extends Factory
{
    protected $model = Percobaan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'jenis' => Percobaan::JENIS_LATIHAN,
            'paket_tryout_id' => null,
            'mapel_id' => null,
            'jumlah_soal' => 10,
            'batas_waktu_menit' => null,
            'status' => Percobaan::STATUS_BERJALAN,
            'daftar_soal' => null,
            'posisi_soal' => 0,
            'urutan_mapel' => 0,
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_detik' => null,
        ];
    }

    public function tryout(): static
    {
        return $this->state(['jenis' => Percobaan::JENIS_TRYOUT]);
    }

    public function selesai(): static
    {
        return $this->state([
            'status' => Percobaan::STATUS_SELESAI,
            'waktu_selesai' => now()->addMinutes(30),
            'durasi_detik' => 1800,
        ]);
    }
}
