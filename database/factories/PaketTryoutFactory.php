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
            'created_by' => null,
        ];
    }

    /**
     * Isinya disusun setelah barisnya ada, persis seperti yang dilakukan form
     * admin: satu baris per mapel beserta paket soal miliknya. Tiga mapel
     * wajib, satu PKK, dan satu pilihan umum — komposisi yang membuat paket
     * ini selalu punya pasangan mapel pilihan untuk dipilih peserta.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PaketTryout $paketTryout): void {
            $paketPerMapel = collect([
                Mapel::factory()->wajib()->create(),
                Mapel::factory()->wajib()->create(),
                Mapel::factory()->wajib()->create(),
                Mapel::factory()->pkk()->create(),
                Mapel::factory()->pilihanUmum()->create(),
            ])->mapWithKeys(fn (Mapel $mapel): array => [
                $mapel->getKey() => PaketSoal::factory()
                    ->create(['mapel_id' => $mapel->getKey()])
                    ->getKey(),
            ]);

            $paketTryout->susunIsiMapel($paketPerMapel->all());
        });
    }
}
