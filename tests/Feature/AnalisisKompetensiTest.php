<?php

use App\Domain\Scoring\IrtService;
use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\Percobaan;
use App\Models\Soal;
use App\Models\TrackingKompetensi;
use App\Models\User;
use Illuminate\Support\Collection;
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
 * Menutup satu sesi latihan berisi seluruh soal `$mapel` pada waktu berjalan.
 *
 * `jumlah_soal` memakai batas atas validasi supaya seluruh soal ikut
 * terambil, sehingga dua sesi memakai himpunan soal yang persis sama dan
 * kompetensi dasar yang tersentuhnya pun sama — tanpa itu seri garis antar
 * tanggal bisa tidak pernah bersinggungan. Payload jawabannya diserahkan ke
 * pemanggil.
 *
 * @param  callable(Collection<int, Soal>): array<string, mixed>  $isiPayload
 */
function latihSesi(TestCase $t, User $peserta, Mapel $mapel, callable $isiPayload): void
{
    $t->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 30,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::latest('id')->firstOrFail();
    $aktif = soalMapelAktif($percobaan);

    $t->actingAs($peserta)->post(
        route('latihan.jawab', $percobaan),
        [...$isiPayload($aktif['soal']), 'aksi' => 'selesai']
    );
}

/**
 * Payload yang menjawab salah pada setiap soal.
 *
 * Dipakai sebagai sesi kedua uji garis perkembangan: sesi pertama seluruhnya
 * benar dan sesi kedua seluruhnya salah membuat theta bergerak jauh, sehingga
 * titik keduanya pasti berbeda. Tanpa perbedaan itu, garis yang menghitung
 * ulang dari seluruh riwayat pada tiap tanggal — datar sepanjang sejarah —
 * akan lulus uji invarian.
 *
 * @param  Collection<int, Soal>|array<int, Soal>  $soals
 * @return array<string, mixed>
 */
function payloadSemuaSalah($soals): array
{
    $payload = ['jawaban' => ['opsi' => [], 'kategori' => []]];

    foreach ($soals as $soal) {
        if ($soal->tipe_soal === Soal::TIPE_PG_KATEGORI) {
            $allowed = array_values((array) $soal->daftar_kategori);

            $payload['jawaban']['kategori'][$soal->id] = $soal->pernyataanKategori
                ->mapWithKeys(fn ($pernyataan) => [
                    $pernyataan->id => collect($allowed)
                        ->first(fn ($kategori): bool => $kategori !== $pernyataan->kategori_benar)
                        ?? $pernyataan->kategori_benar,
                ])->all();

            continue;
        }

        // PG kompleks dinilai per opsi, jadi memilih hanya opsi yang salah
        // membuat seluruh itemnya bernilai 0.
        $salah = $soal->opsiJawaban->filter(fn ($opsi): bool => ! $opsi->is_benar);
        $tunggal = $soal->tipe_soal === Soal::TIPE_PG;

        if ($salah->isEmpty()) {
            $payload['jawaban']['opsi'][$soal->id] = $tunggal ? jawabanBenarSoal($soal) : [];

            continue;
        }

        $payload['jawaban']['opsi'][$soal->id] = $tunggal
            ? $salah->first()->id
            : $salah->pluck('id')->all();
    }

    return $payload;
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

it('menerjemahkan theta menjadi skor IRT pada ringkasan analisis', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $baris = $peserta->trackingMapel()->where('mapel_id', $mapel->getKey())->firstOrFail();

    expect($baris->theta_estimasi)->not->toBeNull();

    $theta = number_format((float) $baris->theta_estimasi, 3, '.', '');
    $skorIrt = (new IrtService)->convertToScale((float) $baris->theta_estimasi, $peserta->tingkat);

    $halaman = $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->assertSee('<dt class="label">Skor IRT</dt>', false);

    expect($halaman->getContent())
        ->toContain((string) $skorIrt)
        ->not->toContain($theta);
});

it('tidak menyebut theta di halaman analisis peserta', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $halaman = $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->getContent();

    $tampilan = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $halaman);

    // Theta adalah statistik psikometrik mentah pada skala −3…+3 yang tidak
    // bisa ditindaklanjuti peserta. Yang mereka baca adalah terjemahannya:
    // Skor IRT di ringkasan dan Level di kartu KD. Label sumbu grafik pun
    // memakai satuan yang sama, jadi istilahnya tak perlu muncul lagi.
    expect(strtolower($tampilan))->not->toContain('theta');
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
    // sebagai legenda garis perkembangan — grafik tak muat memuat deskripsi
    // penuh — dan legenda itu memang tidak terlihat oleh peserta. Yang dinilai
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

it('menyiapkan data grafik radar skor IRT per mapel', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk());

    expect($grafik['radar']['labels'])->toContain($mapel->nama)
        ->and($grafik['radar']['skor'])->toHaveCount(count($grafik['radar']['labels']))
        ->and(array_filter(
            $grafik['radar']['skor'],
            fn ($skor): bool => $skor !== null,
        ))->not->toBeEmpty();
});

it('menyiapkan garis perkembangan skor per kompetensi dasar dari riwayat pengerjaan', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk());

    $seri = $grafik['garis']['seri'];

    expect($grafik['garis']['labels'])->not->toBeEmpty()
        ->and($seri)->not->toBeEmpty()
        ->and(array_column($seri, 'kode'))->toContain($mapel
        ->kompetensiDasars()->orderBy('kode_kompetensi')->first()->kode_kompetensi);

    foreach ($seri as $baris) {
        $terisi = array_filter($baris['nilai'], fn ($skor): bool => $skor !== null);

        expect($baris['nilai'])->toHaveCount(count($grafik['garis']['labels']))
            ->and($terisi)->not->toBeEmpty();

        foreach ($terisi as $skor) {
            expect($skor)->toBeGreaterThanOrEqual($grafik['garis']['batas'][0])
                ->toBeLessThanOrEqual($grafik['garis']['batas'][1]);
        }
    }
});

it('menutup garis perkembangan pada theta yang tersimpan di tracking', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk());

    $kds = $mapel->kompetensiDasars()->get()->keyBy('kode_kompetensi');
    $tracking = $peserta->trackingKompetensi()->get()->keyBy('kompetensi_dasar_id');
    $akhir = count($grafik['garis']['labels']) - 1;

    // Invarian rekonstruksi: garis memotong riwayat pada tanggal, tracking
    // menghitung ulang dari seluruh jawaban — keduanya memakai fungsi estimasi
    // dan himpunan item yang sama, jadi titik terakhir wajib identik. Tanpa
    // jaminan ini grafik bisa menampilkan perkembangan yang berbeda dari
    // angka yang dibaca peserta pada kartu KD.
    foreach ($grafik['garis']['seri'] as $baris) {
        $kd = $kds->get($baris['kode']);
        $tersimpan = $tracking->get($kd?->getKey())?->theta_estimasi;

        expect($kd)->not->toBeNull()
            ->and($tersimpan)->not->toBeNull()
            ->and($baris['nilai'][$akhir])
            ->toBe((new IrtService)->convertToScale((float) $tersimpan, $peserta->tingkat));
    }
});

it('memotong garis perkembangan per tanggal dan mengakumulasikan jawabannya', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    $this->travelTo('2026-09-28 09:00:00');
    latihSesi($this, $peserta, $mapel, fn ($soals) => payloadSemuaBenar($soals));

    $this->travelTo('2026-10-02 09:00:00');
    latihSesi($this, $peserta, $mapel, fn ($soals) => payloadSemuaSalah($soals));

    $grafik = dataGrafik($this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk());

    $kds = $mapel->kompetensiDasars()->get()->keyBy('kode_kompetensi');
    $tracking = $peserta->trackingKompetensi()->get()->keyBy('kompetensi_dasar_id');

    expect($grafik['garis']['labels'])->toBe(['28/09/2026', '02/10/2026'])
        ->and($grafik['garis']['seri'])->not->toBeEmpty();

    $berubah = 0;

    foreach ($grafik['garis']['seri'] as $baris) {
        $tersimpan = $tracking->get($kds->get($baris['kode'])?->getKey())?->theta_estimasi;

        expect($baris['nilai'])->toHaveCount(2)
            ->and($tersimpan)->not->toBeNull()
            ->and($baris['nilai'][1])
            ->toBe((new IrtService)->convertToScale((float) $tersimpan, $peserta->tingkat));

        if ($baris['nilai'][0] !== $baris['nilai'][1]) {
            $berubah++;
        }
    }

    // Sesi kedua menjawab seluruhnya salah, jadi titiknya wajib bergerak. Tanpa
    // ini, garis yang menghitung ulang dari seluruh riwayat pada tiap tanggal
    // — sehingga datar sepanjang sejarah — lulus uji invarian di atas.
    expect($berubah)->toBeGreaterThan(0);
});

it('menampilkan keterangan kosong ketika belum ada riwayat pengerjaan', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    $halaman = $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk()
        ->assertSee('Belum ada latihan atau tryout yang selesai di mapel ini')
        ->assertDontSee('data-grafik="garis"', false);

    $grafik = dataGrafik($halaman);

    expect($grafik['garis']['labels'])->toBeEmpty()
        ->and($grafik['garis']['seri'])->toBeEmpty();
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

it('menyiapkan data grafik garis riwayat skor IRT tryout', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = mapelAnalisis();

    latihSemuaBenar($this, $peserta, $mapel);

    $halaman = fn () => $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk();

    $kosong = dataGrafik($halaman());
    expect($kosong['riwayat']['labels'])->toBeEmpty()
        ->and($kosong['riwayat']['skor'])->toBeEmpty();

    HasilTryout::factory()->for($peserta)->create(['theta_final' => 1.25]);

    // Sumbu-y memakai Skor IRT, bukan theta mentah. Bentuk garisnya persis
    // sama karena keduanya transformasi linear — yang berubah hanya satuan
    // yang dibaca peserta, dari −3…+3 menjadi skala pelaporan akunnya.
    $skor = (new IrtService)->convertToScale(1.25, $peserta->tingkat);

    $terisi = dataGrafik($halaman());
    expect($terisi['riwayat']['skor'])->toBe([$skor])
        ->and($terisi['riwayat']['labels'])->toHaveCount(1);
});
