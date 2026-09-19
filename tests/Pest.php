<?php

use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function mapelSoalF4(Mapel $mapel): Soal
{
    return Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id])->id,
    ]);
}

function paketSoalF4(Mapel $mapel, int $jumlahSoal = 1): PaketSoal
{
    $paket = PaketSoal::factory()->create(['mapel_id' => $mapel->id]);

    for ($i = 0; $i < $jumlahSoal; $i++) {
        DetailPaketSoal::create([
            'paket_soal_id' => $paket->id,
            'soal_id' => mapelSoalF4($mapel)->id,
        ]);
    }

    return $paket;
}
