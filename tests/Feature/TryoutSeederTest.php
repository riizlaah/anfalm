<?php

use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\PaketTryoutMapel;
use App\Models\Soal;
use App\Models\User;
use Database\Seeders\TryoutSeeder;
use Illuminate\Support\Collection;

/**
 * Seluruh baris isi paket tryout, terurut seperti peserta mengerjakannya:
 * mapel wajib lebih dulu, baru mapel pilihan.
 *
 * @return Collection<int, PaketTryoutMapel>
 */
function isiPaketTryout(PaketTryout $paketTryout)
{
    return $paketTryout->daftarMapel()->with('mapel')->get();
}

it('membuat paket tryout berisi seluruh mapel yang layak masuk', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $isi = isiPaketTryout($paketTryout);

    $layak = Mapel::query()
        ->whereNull('deleted_at')
        ->whereNotIn('tingkat', [Mapel::TINGKAT_SD, Mapel::TINGKAT_SMP])
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($isi->pluck('mapel_id')->map(fn ($id) => (int) $id)->all())->toEqualCanonicalizing($layak)
        ->and($isi->pluck('mapel_id')->unique())->toHaveCount(count($layak))
        ->and($layak)->toHaveCount(9);
});

it('tiap baris paket soal dimiliki mapel barisnya dan berisi minimal satu soal', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();

    foreach (isiPaketTryout($paketTryout) as $baris) {
        $paketSoal = PaketSoal::withTrashed()->find((int) $baris->paket_soal_id);

        $this->assertNotNull($paketSoal, "baris mapel {$baris->mapel_id} tidak menunjuk paket soal yang ada");
        $this->assertNull($paketSoal->deleted_at, 'baris menunjuk paket soal yang sudah dihapus');
        $this->assertSame(
            (int) $baris->mapel_id,
            (int) $paketSoal->mapel_id,
            "paket soal pada baris mapel {$baris->mapel_id} bukan milik mapel itu"
        );
        $this->assertGreaterThan(0, $paketSoal->soal()->count(), 'paket soal pada baris tidak berisi soal');
    }
});

it('memenuhi aturan SMK dan kecocokan tingkat pada tiap baris', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $isi = isiPaketTryout($paketTryout);

    expect($paketTryout->tingkat)->toBe(PaketTryout::TINGKAT_SMK);

    $tingkatCocok = [PaketTryout::TINGKAT_SMK, Mapel::TINGKAT_SMA, Mapel::TINGKAT_ALL];

    foreach ($isi as $baris) {
        $this->assertNotNull($baris->mapel, "baris mapel {$baris->mapel_id} tidak menunjuk mapel yang ada");
        $this->assertContains(
            $baris->mapel->tingkat,
            $tingkatCocok,
            "tingkat mapel {$baris->mapel->kode} tidak cocok dengan tingkat tryout"
        );
    }

    $pilihan = $isi
        ->filter(fn ($baris) => $baris->mapel->jenis !== Mapel::JENIS_WAJIB)
        ->pluck('mapel');

    $adaKejuruan = (bool) $pilihan->filter(
        fn (Mapel $mapel): bool => $mapel->jenis === Mapel::JENIS_PILIHAN_KEJURUAN || $mapel->is_pkk
    )->count();

    $this->assertTrue(
        $adaKejuruan,
        'untuk tingkat SMK minimal satu mapel pilihan harus pilihan_kejuruan atau berstatus PKK'
    );

    $this->assertGreaterThanOrEqual(
        2,
        $pilihan->count(),
        'peserta wajib memilih dua mapel pilihan, jadi paket harus menyediakan minimal dua'
    );
});

it('mengisi soal berikut opsinya sehingga tryout benar-benar bisa dijawab', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $paketSoalIds = isiPaketTryout($paketTryout)->pluck('paket_soal_id')->map(fn ($id) => (int) $id)->all();

    $soals = PaketSoal::whereIn('id', $paketSoalIds)->with('soal')->get()->flatMap->soal;

    expect($soals)->not->toBeEmpty();

    foreach ($soals as $soal) {
        if ($soal->tipe_soal === Soal::TIPE_PG_KATEGORI) {
            $this->assertGreaterThanOrEqual(3, $soal->pernyataanKategori()->count(), "soal #{$soal->id} pernyataannya kurang dari 3");
            $this->assertNotEmpty($soal->daftar_kategori, "soal #{$soal->id} tidak punya daftar_kategori");

            continue;
        }

        $jumlahBenar = $soal->opsiJawaban()->where('is_benar', true)->count();
        $isKompleks = $soal->tipe_soal === Soal::TIPE_PG_KOMPLEKS;

        $this->assertGreaterThanOrEqual(5, $soal->opsiJawaban()->count(), "soal #{$soal->id} opsinya kurang dari 5");
        $this->assertGreaterThanOrEqual($isKompleks ? 2 : 1, $jumlahBenar, "soal #{$soal->id} jawaban benarnya kurang");
        $this->assertLessThanOrEqual($isKompleks ? 5 : 1, $jumlahBenar, "soal #{$soal->id} jawaban benarnya kebanyakan");
    }

    $this->assertGreaterThan(
        1,
        $soals->pluck('tipe_soal')->unique()->count(),
        'seharusnya seeder mengisi lebih dari satu tipe soal agar jalur skoring ikut tercakup'
    );
});

it('idempoten: dijalankan berulang tidak menggandakan paket, soal, maupun opsi', function () {
    $this->seed();

    $jumlahOpsi = fn (): int => Soal::withCount('opsiJawaban')->get()->sum('opsi_jawaban_count');
    $sebelum = [
        PaketTryout::count(),
        PaketSoal::count(),
        Soal::count(),
        $jumlahOpsi(),
        KompetensiDasar::count(),
    ];

    (new TryoutSeeder)->run();
    (new TryoutSeeder)->run();

    expect([
        PaketTryout::count(),
        PaketSoal::count(),
        Soal::count(),
        $jumlahOpsi(),
        KompetensiDasar::count(),
    ])->toBe($sebelum);
});

it('melempar error yang jelas ketika mapel tidak mencukupi', function () {
    Mapel::factory()->count(4)->create(['tingkat' => PaketTryout::TINGKAT_SMK]);

    expect(fn () => (new TryoutSeeder)->run())
        ->toThrow(RuntimeException::class, 'minimal 2 mapel pilihan');
});

it('memberi batas waktu 75 menit pada mapel wajib dan 60 menit pada mapel pilihan', function () {
    $this->seed();

    $isi = isiPaketTryout(PaketTryout::firstOrFail());

    $wajib = $isi->filter(fn (PaketTryoutMapel $baris): bool => $baris->mapel->jenis === Mapel::JENIS_WAJIB);
    $pilihan = $isi->filter(fn (PaketTryoutMapel $baris): bool => $baris->mapel->jenis !== Mapel::JENIS_WAJIB);

    expect($wajib)->not->toBeEmpty()
        ->and($pilihan)->not->toBeEmpty()
        ->and($wajib->pluck('menit')->unique()->values()->all())->toBe([75])
        ->and($pilihan->pluck('menit')->unique()->values()->all())->toBe([60]);
});

it('nilai hasil seeder lolos validasi yang sama dengan form admin', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $isi = isiPaketTryout($paketTryout);

    $payload = [
        'nama_paket' => 'Percobaan simpan ulang hasil seeder',
        'deskripsi' => 'Memastikan aturan seeder tidak berbeda dengan aturan controller.',
        'tingkat' => $paketTryout->tingkat,
        'menit' => $isi->mapWithKeys(
            fn ($baris) => [(int) $baris->mapel_id => (int) $baris->menit]
        )->all(),
        'paket_soal' => $isi->mapWithKeys(
            fn ($baris) => [(int) $baris->mapel_id => (int) $baris->paket_soal_id]
        )->all(),
    ];

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.paket-tryout.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));

    expect(PaketTryout::count())->toBe(2);
});
