<?php

use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\Percobaan;
use App\Models\Soal;
use App\Models\TrackingKompetensi;
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

/**
 * Kartu KD yang disorot sebagai fokus belajar berikutnya, kosong bila tidak ada.
 */
function kartuFokusKd(string $html): string
{
    preg_match('/<article[^>]*data-fokus[^>]*>.*?<\/article>/s', $html, $cocok);

    return $cocok[0] ?? '';
}

it('tiap KD yang punya soal mendapat tombol Belajar untuk latihan pada KD itu', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    $kdTanpaSoal = KompetensiDasar::factory()->create([
        'mapel_id' => $mapel->getKey(),
        'kode_kompetensi' => '9.9',
        'deskripsi' => 'KD sementara yang belum punya soal',
    ]);

    $html = $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->getContent();

    preg_match_all('/<form[^>]*action="[^"]*latihan\/mulai"[^>]*>.*?<\/form>/s', $html, $formulir);
    preg_match_all('/<button[^>]*type="submit"[^>]*>Belajar<\/button>/', $html, $tombol);
    preg_match_all('/name="kompetensi_dasar_id" value="(\d+)"/', $html, $kdForm);
    preg_match_all('/name="mapel_id" value="(\d+)"/', $html, $mapelForm);
    preg_match_all('/name="timer" value="(\w+)"/', $html, $timer);

    $kdBersoal = Soal::whereIn(
        'kompetensi_dasar_id',
        $mapel->kompetensiDasars()->pluck('id')
    )->pluck('kompetensi_dasar_id')->unique()->count();

    expect($kdBersoal)->toBeGreaterThan(0)
        ->and($formulir[0])->toHaveCount($kdBersoal)
        ->and($tombol[0])->toHaveCount($kdBersoal)
        ->and(array_unique($kdForm[1]))->toHaveCount($kdBersoal)
        ->and(array_unique($mapelForm[1]))->toBe([(string) $mapel->getKey()])
        ->and(array_unique($timer[1]))->toBe(['stopwatch']);

    expect($kdForm[1])->not->toContain((string) $kdTanpaSoal->getKey());
});

it('menyorot tepat satu KD sebagai fokus belajar berikutnya', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    // Dijamin ada satu KD yang belum tersentuh supaya selalu ada kandidat fokus.
    $kdBelumDilatih = KompetensiDasar::factory()->create([
        'mapel_id' => $mapel->getKey(),
        'kode_kompetensi' => '9.8',
        'deskripsi' => 'KD yang tidak pernah dilatih',
    ]);
    Soal::factory()->create(['kompetensi_dasar_id' => $kdBelumDilatih->getKey()]);

    $html = $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->getContent();

    $fokus = kartuFokusKd($html);

    $pernahDilatih = TrackingKompetensi::where('user_id', $peserta->getKey())
        ->pluck('kompetensi_dasar_id');

    // Kartu tidak lagi memuat kode KD (Fase 14), jadi identitasnya di halaman
    // adalah deskripsinya.
    $belumDilatih = $mapel->kompetensiDasars()
        ->whereNotIn('id', $pernahDilatih)
        ->pluck('deskripsi');

    expect(substr_count($html, 'data-fokus'))->toBe(1)
        ->and($fokus)->not->toBe('')
        ->and($belumDilatih)->not->toBeEmpty()
        ->and($belumDilatih->contains(fn (string $deskripsi): bool => str_contains($fokus, $deskripsi)))
        ->toBeTrue();
});

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
        ->assertSee($kd->deskripsi)
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

it('menyembunyikan kode KD dari halaman analisis siswa', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $kd = $mapel->kompetensiDasars()->orderBy('kode_kompetensi')->first();

    $halaman = $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->getContent();

    // Tag <script dibuang dulu: payload #grafik-analisis masih memuat kode KD
    // sebagai label sumbu grafik batang — grafik tak muat memuat deskripsi
    // penuh — dan label itu memang tidak terlihat oleh peserta. Yang dinilai
    // hanya yang benar-benar dibaca mata.
    $tampilan = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $halaman);

    expect($kd->kode_kompetensi)->not->toBeEmpty()
        ->and($kd->deskripsi)->not->toBeEmpty()
        ->and($tampilan)->not->toContain($kd->kode_kompetensi)
        ->and($tampilan)->toContain($kd->deskripsi);
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
        ->assertSee($kd->deskripsi)
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

it('melengkapi label radar dengan kode singkat supaya muat di layar sempit', function () {
    $peserta = User::factory()->peserta()->create();

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index'))
        ->assertOk());

    $mapels = Mapel::whereNull('deleted_at')->orderBy('kode')->get();

    // Nama lengkap tetap ikut agar tooltip menampilkan "Bahasa Indonesia
    // Tingkat Lanjut" penuh, tetapi label sumbu harus memakai kode: panjang
    // "Pendidikan Pancasila dan Kewarganegaraan" membuat labelnya terpotong di
    // tepi kanvas pada layar 360px.
    expect($grafik['radar']['labels'])->toBe($mapels->pluck('nama')->all())
        ->and($grafik['radar']['singkat'])->toBe($mapels->pluck('kode')->all())
        ->and($grafik['radar']['singkat'])->toHaveCount(count($grafik['radar']['labels']))
        ->and(max(array_map('strlen', $grafik['radar']['singkat'])))->toBeLessThanOrEqual(20);
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
