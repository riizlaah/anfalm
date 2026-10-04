<?php

use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\TrackingMapel;
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

/**
 * Baris `tracking_mapel` yang ditulis persis seperti jalannya produksi:
 * level diturunkan dari theta lewat `KompetensiLevel`, bukan diisi lepas
 * dari theta yang disimpan — kalau tidak, tes bisa lulus dengan data yang
 * tidak pernah bisa ada di aplikasi.
 *
 * `null` berarti peserta belum pernah mengerjakan mapel itu, jadi tidak ada
 * baris yang dibuat sama sekali.
 */
function isiTrackingMapel(User $peserta, Mapel $mapel, ?float $theta): void
{
    if ($theta === null) {
        return;
    }

    TrackingMapel::query()->create([
        'user_id' => $peserta->getKey(),
        'mapel_id' => $mapel->getKey(),
        'theta_estimasi' => $theta,
        'level_kompetensi' => (new KompetensiLevel)->levelFor($theta),
        'last_updated' => now(),
    ]);
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

it('kartu latihan hanya menawarkan mapel wajib dan mapel pilihan yang dipilih', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    // Seeder memberi tiga mapel wajib dan enam pilihan. Peserta hanya memilih
    // satu pilihan, jadi kartunya harus berjumlah empat — bukan sembilan
    // mapel yang ditawarkan halaman Latihan ketika pilihan masih kosong.
    $peserta->mapelPilihan()->sync([
        Mapel::query()->where('nama', 'Kimia')->sole()->getKey(),
    ]);

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count($html, 'data-kartu-latihan'))->toBe(4)
        ->and($html)
        ->toContain('Matematika')
        ->toContain('Kimia')
        ->not->toContain('Fisika');
});

it('kartu latihan berhenti di mapel wajib ketika peserta belum memilih pilihan', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count($html, 'data-kartu-latihan'))->toBe(3)
        ->and($html)->not->toContain('Fisika');
});

it('ketiga blok dashboard berbagi lebar yang sama dan grid latihan dua kolom', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    // Asimetris di desktop (butir laporan): grid latihan berdiri di `max-w-5xl`
    // sementara kartu streak dan tryout terbaru berhenti di `max-w-3xl`, jadi
    // tepi kanan halaman terpotong-potong. Kini ketiganya `max-w-3xl`, dan
    // gridnya dua kolom — tiga kolom butuh lebar yang justru memaksa blok di
    // atasnya melebar lagi.
    expect($html)
        ->not->toContain('max-w-5xl')
        ->not->toContain('lg:grid-cols-3')
        ->toContain('sm:grid-cols-2')
        ->toContain('max-w-3xl');
});

it('kartu latihan menampilkan ajakan dan tombol yang berbeda menurut level', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $peserta = User::factory()->peserta()->create();

    // Tiga mapel wajib bawaan seeder diberi tiga theta yang menghasilkan tiga
    // level berbeda, lalu dua mapel wajib tambahan melengkapi Dasar dan
    // keadaan tanpa baris tracking sama sekali.
    isiTrackingMapel($peserta, Mapel::query()->where('nama', 'Matematika')->sole(), 2.0);
    isiTrackingMapel($peserta, Mapel::query()->where('nama', 'Bahasa Indonesia')->sole(), 1.0);
    isiTrackingMapel($peserta, Mapel::query()->where('nama', 'Bahasa Inggris')->sole(), -1.0);

    Mapel::factory()->wajib()->create(['nama' => 'Mapel Diberi Dasar']);
    isiTrackingMapel($peserta, Mapel::query()->where('nama', 'Mapel Diberi Dasar')->sole(), 0.0);

    Mapel::factory()->wajib()->create(['nama' => 'Mapel Tanpa Data']);

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    // Label tombol adalah pembeda yang paling langsung terbaca: lima level
    // harus menghasilkan lima ajakan yang berbeda, bukan satu tombol yang
    // sama berkedok dengan kalimat berbeda.
    expect($html)
        ->toContain('Pertahankan')
        ->toContain('Kejar Mahir')
        ->toContain('Perkuat Dasar')
        ->toContain('Latih Sekarang')
        ->toContain('Mulai Latihan')
        ->toContain('Belum Teridentifikasi');
});

it('dashboard tidak menampilkan kartu latihan di admin', function () {
    $this->travelTo('2026-10-03 10:00:00');

    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('data-kartu-latihan');
});
