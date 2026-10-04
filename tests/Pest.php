<?php

use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

/**
 * Sepasang mapel pilihan yang dipakai tes memulai tryout: dua mapel pilihan
 * pertama milik paket itu, terurut menurut kode mapel seperti peserta
 * memilihnya di halaman pilihan.
 *
 * @return array<int, int>
 */
function duaMapelPilihan(PaketTryout $paket): array
{
    return $paket->daftarMapel()
        ->with('mapel')
        ->get()
        ->filter(fn ($baris) => $baris->mapel?->jenis !== Mapel::JENIS_WAJIB)
        ->sortBy(fn ($baris) => $baris->mapel->kode)
        ->take(2)
        ->pluck('mapel_id')
        ->all();
}

/**
 * Daftar soal pada posisi mapel yang sedang dikerjakan, terurut sesuai
 * acakan percobaan. Berlaku untuk tryout maupun latihan (satu kelompok).
 *
 * @return array{soal: Collection<int, Soal>, urut: int}
 */
function soalMapelAktif(Percobaan $percobaan): array
{
    $grup = $percobaan->daftar_soal[$percobaan->urutan_mapel];

    return [
        'soal' => Soal::with(['opsiJawaban', 'pernyataanKategori'])
            ->whereIn('id', $grup['soal_ids'])
            ->get()
            ->sortBy(fn (Soal $soal) => array_search($soal->id, $grup['soal_ids']))
            ->values(),
        'urut' => $grup['mapel_id'],
    ];
}

/**
 * Jawaban paling benar untuk satu soal, dalam bentuk yang dipakai form.
 *
 * @return array<int, mixed>|int
 */
function jawabanBenarSoal(Soal $soal): mixed
{
    return match ($soal->tipe_soal) {
        Soal::TIPE_PG => $soal->opsiJawaban->firstWhere('is_benar', true)->id,
        Soal::TIPE_PG_KOMPLEKS => $soal->opsiJawaban->filter->is_benar->pluck('id')->all(),
        Soal::TIPE_PG_KATEGORI => $soal->pernyataanKategori
            ->mapWithKeys(fn ($p) => [$p->id => $p->kategori_benar])
            ->all(),
    };
}

/**
 * Payload `jawaban[...]` untuk sekumpulan soal, memilih semua jawaban benar.
 *
 * @param  Collection<int, Soal>|array<int, Soal>  $soals
 * @return array<string, mixed>
 */
function payloadSemuaBenar($soals): array
{
    $opsi = [];
    $kategori = [];

    foreach ($soals as $soal) {
        if ($soal->tipe_soal === Soal::TIPE_PG_KATEGORI) {
            $kategori[$soal->id] = jawabanBenarSoal($soal);

            continue;
        }

        $opsi[$soal->id] = jawabanBenarSoal($soal);
    }

    return ['jawaban' => ['opsi' => $opsi, 'kategori' => $kategori]];
}
