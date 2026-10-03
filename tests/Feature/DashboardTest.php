<?php

use App\Models\HasilTryout;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\User;

beforeEach(function () {
    $this->seed();
});

/**
 * Satu percobaan yang dimulai pada hari tertentu, dihitung mundur dari hari
 * yang dibekukan. Streak dihitung per tanggal, jadi `waktu_mulai` satu-satunya
 * kolom yang perlu diatur.
 */
function percobaanPada(User $peserta, int $mundurHari, int $jumlah = 1): void
{
    Percobaan::factory()->count($jumlah)->create([
        'user_id' => $peserta->getKey(),
        'waktu_mulai' => now()->subDays($mundurHari)->setTime(9, 0),
    ]);
}

/**
 * Sel bertanggal `$tanggal` memuat tingkat yang diharapkan. Pencarian lewat
 * reguler karena atributnya terbagi beberapa baris di dalam komponen.
 */
function selBertingkat(string $html, string $tanggal, int $tingkat): bool
{
    return preg_match('/data-tanggal="'.$tanggal.'"\s+data-tingkat="'.$tingkat.'"/', $html) === 1;
}

it('menampilkan kalender aktivitas dengan streak hari beruntun di dashboard peserta', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    // Hari ini dan kemarin terisi, sebelumnya kosong: streak berhenti di dua.
    percobaanPada($peserta, 0);
    percobaanPada($peserta, 1, 3);

    // Peserta lain mengisi hari celahnya. Kalau aktivitasnya ikut terbaca,
    // streak-nya jadi tiga dan tes ini gagal.
    percobaanPada(User::factory()->peserta()->create(), 2, 5);

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-streak="2"')
        ->toContain('data-tanggal="2026-10-03"')
        ->toContain('data-tanggal="2026-10-02"')
        ->and(substr_count($html, 'data-tanggal='))->toBe(56);

    // Satu percobaan hari ini dan tiga percobaan kemarin harus terhitung
    // sebagai agregat per hari, bukan sekadar ada-tidaknya baris.
    expect(selBertingkat($html, '2026-10-03', 1))->toBeTrue()
        ->and(selBertingkat($html, '2026-10-02', 2))->toBeTrue();
});

it('menampilkan ajakan memulai streak saat peserta belum pernah beraktivitas', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-streak="0"')
        ->toContain('Belum ada aktivitas');
});

it('tidak menampilkan kalender aktivitas di dashboard admin', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)
        ->not->toContain('data-streak')
        ->not->toContain('data-tanggal=');
});

it('dashboard menampilkan tryout terbaru yang belum diikuti beserta ajakan memulainya', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    // Urutan pembuatan sengaja dibalik terhadap urutan abjad: paket terbaru
    // (id terbesar) ada di tengah daftar alfabetis, sehingga kartu yang
    // mengambil paket pertama — atau yang memakai urutan nama seperti
    // halaman daftar tryout — menampilkan paket yang salah dan tes ini gagal.
    PaketTryout::factory()->create(['nama_paket' => 'Tryout Zulu Lama']);
    $terbaru = PaketTryout::factory()->create(['nama_paket' => 'Tryout Mike Terbaru']);

    $this->actingAs($peserta)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tryout Mike Terbaru')
        ->assertSee('Belum kamu selesaikan')
        ->assertSee(route('tryout.mulai', $terbaru), false)
        ->assertDontSee('Tryout Zulu Lama')
        ->assertDontSee('Tryout Fase 6 (uji coba)')
        ->assertDontSee(route('tryout.hasil', $terbaru), false);
});

it('dashboard menampilkan hasil peserta ketika tryout terbaru sudah diikuti', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();
    $terbaru = PaketTryout::factory()->create(['nama_paket' => 'Tryout Mike Terbaru']);

    HasilTryout::factory()->for($peserta)->create([
        'paket_tryout_id' => $terbaru->getKey(),
        'skor_konversi' => 547,
        'jumlah_benar' => 41,
        'total_soal' => 50,
    ]);

    $this->actingAs($peserta)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tryout Mike Terbaru')
        ->assertSee('547')
        ->assertSee(route('tryout.hasil', $terbaru), false)
        ->assertDontSee('Belum kamu selesaikan')
        ->assertDontSee(route('tryout.mulai', $terbaru), false);
});

it('dashboard menampilkan keterangan kosong ketika belum ada paket tryout', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    PaketTryout::query()->delete();

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)
        ->toContain('Belum ada paket tryout')
        ->not->toContain('tryout/mulai')
        ->not->toContain('tryout/hasil');
});

it('dashboard tidak menampilkan informasi tryout di admin', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)
        ->not->toContain('Tryout Fase 6 (uji coba)')
        ->not->toContain('Belum kamu selesaikan');
});
