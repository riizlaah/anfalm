<?php

use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use App\Models\TrackingKompetensi;
use App\Models\User;

beforeEach(function () {
    $this->seed();
});

/**
 * @return array{mapel: Mapel, kd: KompetensiDasar}
 */
function mapelLatihan(): array
{
    $mapel = Mapel::whereNull('deleted_at')->orderBy('id')->first();

    return [
        'mapel' => $mapel,
        'kd' => $mapel->kompetensiDasars()->orderBy('id')->first(),
    ];
}

it('mengalihkan tamu ke halaman login', function () {
    $this->get(route('latihan.index'))->assertRedirect(route('login'));
});

it('menampilkan form latihan berisi pilihan mapel dan timer', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)
        ->get(route('latihan.index'))
        ->assertOk()
        ->assertSee($mapel->nama)
        ->assertSee('Stopwatch')
        ->assertSee('Countdown')
        ->assertSee('5');
});

it('membuat percobaan latihan sesuai pilihan peserta', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)
        ->post(route('latihan.mulai'), [
            'mapel_id' => $mapel->getKey(),
            'jumlah_soal' => 5,
            'timer' => 'stopwatch',
        ])
        ->assertRedirect(route('latihan.kerja', Percobaan::sole()));

    $percobaan = Percobaan::sole();

    expect($percobaan->jenis)->toBe(Percobaan::JENIS_LATIHAN)
        ->and($percobaan->paket_tryout_id)->toBeNull()
        ->and($percobaan->mapel_id)->toBe($mapel->getKey())
        ->and($percobaan->jumlah_soal)->toBe(5)
        ->and($percobaan->batas_waktu_menit)->toBeNull()
        ->and($percobaan->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and($percobaan->daftar_soal)->toHaveCount(1);

    $grup = $percobaan->daftar_soal[0];

    expect($grup['mapel_id'])->toBe($mapel->getKey())
        ->and($grup['soal_ids'])->toHaveCount(5);

    $kdMapel = $mapel->kompetensiDasars()->pluck('id');

    expect(
        Soal::whereIn('id', $grup['soal_ids'])->pluck('kompetensi_dasar_id')->unique()->diff($kdMapel)
    )->toBeEmpty();
});

it('meminta jumlah soal sesuai pilihan, termasuk bila melebihi yang tersedia', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();
    $tersedia = Soal::whereHas('kompetensiDasar', fn ($q) => $q->where('mapel_id', $mapel->getKey()))->count();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'stopwatch',
    ]);

    expect(Percobaan::latest('id')->first()->daftar_soal[0]['soal_ids'])->toHaveCount(3);

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 30,
        'timer' => 'stopwatch',
    ]);

    expect(Percobaan::latest('id')->first()->daftar_soal[0]['soal_ids'])->toHaveCount($tersedia);
});

it('menolak jumlah soal di luar rentang satu sampai tiga puluh', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)
        ->from(route('latihan.index'))
        ->post(route('latihan.mulai'), [
            'mapel_id' => $mapel->getKey(),
            'jumlah_soal' => 31,
            'timer' => 'stopwatch',
        ])
        ->assertRedirect(route('latihan.index'))
        ->assertSessionHasErrors('jumlah_soal');

    expect(Percobaan::count())->toBe(0);
});

it('membatasi soal sesuai filter kompetensi dasar', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $soalTerpilih = mapelSoalF4($mapel);

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 5,
        'kompetensi_dasar_id' => $soalTerpilih->kompetensi_dasar_id,
        'timer' => 'stopwatch',
    ]);

    expect(Percobaan::sole()->daftar_soal[0]['soal_ids'])->toBe([$soalTerpilih->getKey()]);
});

it('menyimpan batas waktu hanya untuk timer hitung mundur', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'countdown',
        'batas_waktu_menit' => 10,
    ]);

    $percobaan = Percobaan::sole();

    expect($percobaan->batas_waktu_menit)->toBe(10);

    $this->actingAs($peserta)
        ->get(route('latihan.kerja', $percobaan))
        ->assertOk()
        ->assertSee($percobaan->waktu_mulai->copy()->addMinutes(10)->toIso8601String());
});

it('memakai stopwatch tanpa hitung mundur', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();

    $this->actingAs($peserta)
        ->get(route('latihan.kerja', $percobaan))
        ->assertOk()
        ->assertSee($percobaan->waktu_mulai->toIso8601String())
        ->assertDontSee('data-batas');
});

it('menghalangi peserta lain membuka percobaan latihan', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();

    $this->actingAs(User::factory()->peserta()->create())
        ->get(route('latihan.kerja', $percobaan))
        ->assertForbidden();

    $this->actingAs(User::factory()->peserta()->create())
        ->post(route('latihan.jawab', $percobaan))
        ->assertForbidden();
});

it('menyimpan jawaban lalu menutup latihan dan memperbarui tracking kompetensi', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 6,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)
        ->post(route('latihan.jawab', $percobaan), [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'selesai'])
        ->assertRedirect(route('latihan.hasil', $percobaan));

    expect($percobaan->refresh()->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and(RiwayatPengerjaan::count())->toBe($aktif['soal']->count())
        ->and(HasilTryout::count())->toBe(0);

    $tracking = TrackingKompetensi::where('user_id', $peserta->getKey())->get();

    expect($tracking)->toHaveCount(1)
        ->and($tracking->first()->kompetensi_dasar_id)->toBe($aktif['soal']->first()->kompetensi_dasar_id)
        ->and($tracking->first()->total_soal_dikerjakan)->toBe($aktif['soal']->count())
        ->and($tracking->first()->total_benar)->toBe($aktif['soal']->count())
        ->and($tracking->first()->persentase_benar)->toEqualWithDelta(100.0, 0.01)
        ->and($tracking->first()->theta_estimasi)->toEqualWithDelta(3.0, 0.01)
        ->and($tracking->first()->last_updated)->not->toBeNull();
});

it('menolak percobaan tryout lewat jalur latihan', function () {
    $peserta = User::factory()->peserta()->create();
    $paket = PaketTryout::firstOrFail();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $percobaan = Percobaan::sole();

    $this->actingAs($peserta)->get(route('latihan.kerja', $percobaan))->assertNotFound();
    $this->actingAs($peserta)->post(route('latihan.jawab', $percobaan))->assertNotFound();
});

it('menutup latihan sendiri saat batas waktu sudah terlampaui', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'countdown',
        'batas_waktu_menit' => 10,
    ]);

    $percobaan = Percobaan::sole();
    $percobaan->update(['waktu_mulai' => now()->subMinutes(15)]);

    // Peserta kembali setelah tutup: halaman tidak menampilkan soal lagi.
    $this->actingAs($peserta)
        ->get(route('latihan.kerja', $percobaan))
        ->assertRedirect(route('latihan.hasil', $percobaan));

    expect($percobaan->refresh()->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and($percobaan->durasi_detik)->toBe(600);
});

it('menyimpan jawaban yang telat lalu tetap menutup latihan', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 6,
        'timer' => 'countdown',
        'batas_waktu_menit' => 10,
    ]);

    $percobaan = Percobaan::sole();
    $percobaan->update(['waktu_mulai' => now()->subMinutes(15)]);

    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)
        ->post(route('latihan.jawab', $percobaan), [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'simpan'])
        ->assertRedirect(route('latihan.hasil', $percobaan));

    expect($percobaan->refresh()->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and(RiwayatPengerjaan::count())->toBe($aktif['soal']->count())
        ->and(TrackingKompetensi::count())->toBeGreaterThan(0);
});

it('menyimpan latihan tanpa menutupnya sehingga bisa dilanjutkan', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 6,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)
        ->post(route('latihan.jawab', $percobaan), [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'simpan'])
        ->assertRedirect(route('latihan.index'));

    expect($percobaan->refresh()->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and(RiwayatPengerjaan::count())->toBe($aktif['soal']->count())
        ->and(TrackingKompetensi::count())->toBe(0);

    // Sesi yang masih berjalan ditawarkan lagi dari halaman latihan, dan
    // jawaban yang tadi disimpan ikut tampil kembali.
    $this->actingAs($peserta)
        ->get(route('latihan.index'))
        ->assertOk()
        ->assertSee(route('latihan.kerja', $percobaan));

    $this->actingAs($peserta)
        ->get(route('latihan.kerja', $percobaan))
        ->assertOk()
        ->assertSee($aktif['soal']->first()->pertanyaan);
});

it('menampilkan hasil latihan dengan jumlah benar dan level kompetensi', function () {
    $peserta = User::factory()->peserta()->create();
    ['mapel' => $mapel] = mapelLatihan();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 6,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)->post(
        route('latihan.jawab', $percobaan),
        [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'selesai']
    );

    $this->actingAs($peserta)
        ->get(route('latihan.hasil', $percobaan))
        ->assertOk()
        ->assertSee((string) $aktif['soal']->count())
        ->assertSee('Mahir');
});
