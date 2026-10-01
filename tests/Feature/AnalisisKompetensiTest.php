<?php

use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\Mapel;
use App\Models\Percobaan;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    $this->seed();
});

/**
 * Mapel yang punya soal, dipakai sebagai sumber data latihan di test.
 */
function mapelAnalisis(): Mapel
{
    return Mapel::whereNull('deleted_at')->orderBy('id')->first();
}

/**
 * Menutup satu latihan penuh pada `$mapel` dengan seluruh jawaban benar.
 */
function latihSemuaBenar(TestCase $t, User $peserta, Mapel $mapel): void
{
    $t->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 6,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $t->actingAs($peserta)->post(
        route('latihan.jawab', $percobaan),
        [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'selesai']
    );
}

/**
 * Menarik payload JSON tiga grafik analisis dari hasil render halaman.
 *
 * @return array<string, array<string, array<int, mixed>>>
 */
function dataGrafik(TestResponse $halaman): array
{
    $ada = preg_match(
        '/<script type="application\/json" id="grafik-analisis">(.*?)<\/script>/s',
        $halaman->getContent(),
        $cocok,
    );

    expect($ada)->toBe(1);

    $data = json_decode($cocok[1], true);

    expect(is_array($data))->toBeTrue();

    return $data;
}

it('mengalihkan tamu ke halaman login', function () {
    $this->get(route('analisis.index'))->assertRedirect(route('login'));
});

it('menampilkan dropdown mapel dan analisis per KD setelah peserta berlatih', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $kd = $mapel->kompetensiDasars()->orderBy('kode_kompetensi')->first();

    $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->assertSee('Analisis Kompetensi')
        ->assertSee('name="mapel_id"', false)
        ->assertSee($kd->kode_kompetensi)
        ->assertSee((new KompetensiLevel)->label(KompetensiLevel::MAHIR))
        ->assertSee('100')
        ->assertSee((new KompetensiLevel)->rekomendasi(KompetensiLevel::MAHIR));
});

it('menampilkan theta dengan tiga desimal pada tabel analisis', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $theta = $peserta->trackingKompetensi()->first()->theta_estimasi;

    $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->assertSee(number_format((float) $theta, 3, '.', ''));
});

it('menandai KD yang belum pernah dikerjakan sebagai belum teridentifikasi', function () {
    $peserta = User::factory()->peserta()->create();

    $mapelDikerjakan = mapelAnalisis();
    latihSemuaBenar($this, $peserta, $mapelDikerjakan);

    $mapelKosong = Mapel::whereNull('deleted_at')
        ->where('id', '!=', $mapelDikerjakan->getKey())
        ->orderBy('id')
        ->first();

    $kd = $mapelKosong->kompetensiDasars()->orderBy('kode_kompetensi')->firstOrFail();

    $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapelKosong->getKey()]))
        ->assertOk()
        ->assertSee($kd->kode_kompetensi)
        ->assertSee((new KompetensiLevel)->label(KompetensiLevel::BELUM_TERIDENTIFIKASI))
        ->assertSee((new KompetensiLevel)->rekomendasi(KompetensiLevel::BELUM_TERIDENTIFIKASI));
});

it('meringkas tracking mapel di bagian atas halaman analisis', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $baris = $peserta->trackingMapel()->where('mapel_id', $mapel->getKey())->firstOrFail();

    expect($baris->total_tryout_diikuti)->toBe(0)
        ->and($baris->theta_estimasi)->not->toBeNull();

    $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->assertSee('Ringkasan peta kompetensi')
        ->assertSee('Jumlah tryout')
        ->assertSee((new KompetensiLevel)->label($baris->level_kompetensi));
});

it('menyiapkan data grafik radar theta per mapel', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk());

    expect($grafik['radar']['labels'])->toContain($mapel->nama)
        ->and($grafik['radar']['theta'])->toHaveCount(count($grafik['radar']['labels']))
        ->and(array_filter(
            $grafik['radar']['theta'],
            fn ($theta): bool => $theta !== null,
        ))->not->toBeEmpty();
});

it('menyiapkan data grafik batang level per KD terpilih', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $kd = $mapel->kompetensiDasars()->orderBy('kode_kompetensi')->first();

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk());

    // Latihan tadi 100% benar, jadi setidaknya satu KD sudah Mahir (urutan 4).
    expect($grafik['level']['labels'])->toContain($kd->kode_kompetensi)
        ->and($grafik['level']['nilai'])->not->toBeEmpty()
        ->and(min($grafik['level']['nilai']))->toBeGreaterThanOrEqual(0)
        ->and(max($grafik['level']['nilai']))->toBeLessThanOrEqual(4)
        ->and($grafik['level']['nilai'])->toContain(4)
        ->and($grafik['level']['level'])->toContain((new KompetensiLevel)->label(KompetensiLevel::MAHIR));
});

it('menyiapkan data grafik garis riwayat nilai tryout', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $halaman = fn () => $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk();

    $kosong = dataGrafik($halaman());
    expect($kosong['riwayat']['labels'])->toBeEmpty()
        ->and($kosong['riwayat']['theta'])->toBeEmpty();

    HasilTryout::factory()->for($peserta)->create(['theta_final' => 1.25]);

    $terisi = dataGrafik($halaman());
    expect($terisi['riwayat']['theta'])->toBe([1.25])
        ->and($terisi['riwayat']['labels'])->toHaveCount(1);
});
