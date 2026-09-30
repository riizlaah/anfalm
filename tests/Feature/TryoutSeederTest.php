<?php

use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Soal;
use App\Models\User;
use Database\Seeders\TryoutSeeder;

/**
 * Slot mapel dan slot paket soal harus berurutan sama, karena posisi ke-i dari
 * keduanya saling terikat pada satu baris paket tryout.
 *
 * @return array{mapel: array<int, string>, paket: array<int, string>}
 */
function slotTryoutFase6(): array
{
    return [
        'mapel' => ['mapel_wajib_1', 'mapel_wajib_2', 'mapel_wajib_3', 'mapel_pilihan_1', 'mapel_pilihan_2'],
        'paket' => [
            'paket_soal_wajib_1_id',
            'paket_soal_wajib_2_id',
            'paket_soal_wajib_3_id',
            'paket_soal_pilihan_1_id',
            'paket_soal_pilihan_2_id',
        ],
    ];
}

function nilaiSlot(PaketTryout $paketTryout, string $slot): int
{
    return (int) $paketTryout->getAttribute($slot);
}

it('membuat paket tryout dengan lima slot mapel yang berbeda', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $mapelIds = array_map(fn (string $slot) => nilaiSlot($paketTryout, $slot), slotTryoutFase6()['mapel']);

    expect($mapelIds)->toHaveCount(5)
        ->and($mapelIds)->not->toContain(0)
        ->and(array_unique($mapelIds))->toHaveCount(5)
        ->and(Mapel::whereIn('id', $mapelIds)->count())->toBe(5);
});

it('tiap slot paket soal dimiliki mapel slotnya dan berisi minimal satu soal', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $slots = slotTryoutFase6();

    foreach ($slots['paket'] as $index => $slotPaket) {
        $slotMapel = $slots['mapel'][$index];
        $paketSoal = PaketSoal::withTrashed()->find(nilaiSlot($paketTryout, $slotPaket));

        $this->assertNotNull($paketSoal, "slot {$slotPaket} tidak menunjuk paket soal yang ada");
        $this->assertNull($paketSoal->deleted_at, "slot {$slotPaket} menunjuk paket soal yang sudah dihapus");
        $this->assertSame(
            nilaiSlot($paketTryout, $slotMapel),
            $paketSoal->mapel_id,
            "paket soal di slot {$slotPaket} bukan milik mapel di slot {$slotMapel}"
        );
        $this->assertGreaterThan(0, $paketSoal->soal()->count(), "paket soal di slot {$slotPaket} tidak berisi soal");
    }
});

it('memenuhi aturan SMK dan kecocokan tingkat pada tiap slot', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $slots = slotTryoutFase6();

    expect($paketTryout->tingkat)->toBe(PaketTryout::TINGKAT_SMK);

    $mapels = Mapel::query()
        ->whereIn('id', array_map(fn (string $slot) => nilaiSlot($paketTryout, $slot), $slots['mapel']))
        ->get()
        ->keyBy('id');

    $tingkatCocok = [PaketTryout::TINGKAT_SMK, Mapel::TINGKAT_SMA, Mapel::TINGKAT_ALL];

    foreach ($slots['mapel'] as $slot) {
        $mapel = $mapels->get(nilaiSlot($paketTryout, $slot));

        $this->assertNotNull($mapel, "slot {$slot} tidak menunjuk mapel yang ada");
        $this->assertContains(
            $mapel->tingkat,
            $tingkatCocok,
            "tingkat mapel {$mapel->kode} tidak cocok dengan tingkat tryout"
        );
    }

    $pilihan = array_map(
        fn (string $slot) => $mapels->get(nilaiSlot($paketTryout, $slot)),
        ['mapel_pilihan_1', 'mapel_pilihan_2']
    );

    $adaKejuruan = (bool) array_filter(
        $pilihan,
        fn (Mapel $mapel): bool => $mapel->jenis === Mapel::JENIS_PILIHAN_KEJURUAN || $mapel->is_pkk
    );

    $this->assertTrue(
        $adaKejuruan,
        'untuk tingkat SMK minimal satu mapel pilihan harus pilihan_kejuruan atau berstatus PKK'
    );
});

it('mengisi soal berikut opsinya sehingga tryout benar-benar bisa dijawab', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $paketSoalIds = array_map(fn (string $slot) => nilaiSlot($paketTryout, $slot), slotTryoutFase6()['paket']);

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
        ->toThrow(RuntimeException::class, 'minimal 5 mapel');
});

it('nilai hasil seeder lolos validasi yang sama dengan form admin', function () {
    $this->seed();

    $paketTryout = PaketTryout::firstOrFail();
    $slots = slotTryoutFase6();

    $payload = [
        'nama_paket' => 'Percobaan simpan ulang hasil seeder',
        'deskripsi' => 'Memastikan aturan seeder tidak berbeda dengan aturan controller.',
        'tingkat' => $paketTryout->tingkat,
        'batas_waktu_menit' => $paketTryout->batas_waktu_menit,
    ];

    foreach ($slots['mapel'] as $slot) {
        $payload[$slot] = nilaiSlot($paketTryout, $slot);
    }

    foreach ($slots['paket'] as $slot) {
        $payload[$slot] = nilaiSlot($paketTryout, $slot);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.paket-tryout.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));

    expect(PaketTryout::count())->toBe(2);
});
