<?php

namespace Database\Factories;

use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiwayatPengerjaanFactory extends Factory
{
    protected $model = RiwayatPengerjaan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'percobaan_id' => Percobaan::factory(),
            'soal_id' => Soal::factory(),
            'paket_tryout_id' => null,
            'jawaban_user' => ['opsi' => 'A'],
            'is_benar' => true,
            'mode' => 'latihan',
            'waktu_mulai' => now()->subMinutes(2),
            'waktu_selesai' => now(),
            'durasi_detik' => 120,
            'theta_est_moment' => null,
            'skor_irt' => null,
        ];
    }
}