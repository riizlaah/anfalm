<?php

use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use App\Models\TrackingKompetensi;
use App\Models\TrackingMapel;
use App\Models\User;

/**
 * Payload form admin: satu peta `paket_soal[mapel_id] => paket_soal_id`
 * berisi setiap mapel yang dibuat, persis seperti yang dikirim browser.
 *
 * @return array{payload: array<string, mixed>, mapels: array<string, Mapel>, pakets: array<string, PaketSoal>}
 */
function setupTryoutF4(string $tingkat = 'SMK', bool $pilihanPertamaKejuruan = true, ?string $mapelTingkat = null): array
{
    $mapelTingkat ??= $tingkat;

    $mapels = [
        'wajib1' => Mapel::factory()->state(['jenis' => Mapel::JENIS_WAJIB])->create(['tingkat' => $mapelTingkat]),
        'wajib2' => Mapel::factory()->state(['jenis' => Mapel::JENIS_WAJIB])->create(['tingkat' => $mapelTingkat]),
        'wajib3' => Mapel::factory()->state(['jenis' => Mapel::JENIS_WAJIB])->create(['tingkat' => $mapelTingkat]),
        'pilihan1' => $pilihanPertamaKejuruan
            ? Mapel::factory()->state(['jenis' => Mapel::JENIS_PILIHAN_KEJURUAN])->create(['tingkat' => $mapelTingkat])
            : Mapel::factory()->state(['jenis' => Mapel::JENIS_PILIHAN_UMUM])->create(['tingkat' => $mapelTingkat]),
        'pilihan2' => Mapel::factory()->state(['jenis' => Mapel::JENIS_PILIHAN_UMUM])->create(['tingkat' => $mapelTingkat]),
    ];

    $pakets = [];
    $paketPerMapel = [];
    $menitPerMapel = [];

    foreach (['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'] as $slot) {
        $pakets[$slot] = paketSoalF4($mapels[$slot]);
        $paketPerMapel[$mapels[$slot]->getKey()] = $pakets[$slot]->getKey();
        $menitPerMapel[$mapels[$slot]->getKey()] = $mapels[$slot]->jenis === Mapel::JENIS_WAJIB ? 75 : 60;
    }

    $payload = [
        'nama_paket' => 'Tryout '.$tingkat.' '.fake()->word(),
        'deskripsi' => null,
        'tingkat' => $tingkat,
        'menit' => $menitPerMapel,
        'paket_soal' => $paketPerMapel,
    ];

    return ['payload' => $payload, 'mapels' => $mapels, 'pakets' => $pakets];
}

/** Seluruh baris isi paket, dalam bentuk `mapel_id => paket_soal_id`. */
function isiPaketTryoutAdmin(PaketTryout $paketTryout)
{
    return $paketTryout->daftarMapel()
        ->pluck('paket_soal_id', 'mapel_id')
        ->map(fn ($paketId): int => (int) $paketId);
}

it('tamu yang membuka /admin/paket-tryout dialihkan ke login', function () {
    $this->get('/admin/paket-tryout')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin/paket-tryout (403)', function () {
    $user = User::factory()->peserta()->create();

    $this->actingAs($user)->get('/admin/paket-tryout')->assertForbidden();
});

it('admin dapat membuka halaman index paket tryout', function () {
    $admin = User::factory()->admin()->create();
    PaketTryout::factory()->create(['nama_paket' => 'Tryout TKA SMK 2025']);

    $this->actingAs($admin)->get('/admin/paket-tryout')
        ->assertOk()
        ->assertSee('Manajemen Paket Tryout')
        ->assertSee('Tryout TKA SMK 2025');
});

it('admin dapat membuat paket tryout SMK yang valid (kejuruan + umum)', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'nama_paket' => $data['payload']['nama_paket'],
        'tingkat' => 'SMK',
    ]);

    $paketTryout = PaketTryout::sole();

    expect($paketTryout->daftarMapel()->count())->toBe(5)
        ->and($paketTryout->daftarMapel->pluck('menit')->unique()->values()->all())
        ->toEqualCanonicalizing([75, 60]);
});

it('form paket tryout menawarkan batas waktu menit tersendiri tiap baris mapel', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $html = $this->actingAs($admin)->get('/admin/paket-tryout/create')
        ->assertOk()
        ->getContent();

    foreach ($data['mapels'] as $mapel) {
        $bawaan = $mapel->jenis === Mapel::JENIS_WAJIB ? 75 : 60;

        expect($html)->toMatch(
            '/<input[^>]*name="menit\['.$mapel->getKey().'\]"[^>]*value="'.$bawaan.'"/'
        );
    }

    // Tidak ada lagi satu angka waktu yang dipakai seluruh paket.
    expect($html)->not->toContain('name="batas_waktu_menit"');
});

it('menyimpan batas waktu menit yang berbeda untuk tiap mapel', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $wajib1 = $data['mapels']['wajib1']->getKey();
    $data['payload']['menit'][$wajib1] = 90;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));

    $tersimpan = PaketTryout::sole()->daftarMapel()
        ->pluck('menit', 'mapel_id')
        ->map(fn ($menit): int => (int) $menit);

    expect($tersimpan->get($wajib1))->toBe(90)
        ->and($tersimpan->get($data['mapels']['wajib2']->getKey()))->toBe(75)
        ->and($tersimpan->get($data['mapels']['pilihan1']->getKey()))->toBe(60);
});

it('menolak batas waktu menit yang kosong atau di luar rentang', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    unset($data['payload']['menit'][$data['mapels']['wajib2']->getKey()]);
    $data['payload']['menit'][$data['mapels']['pilihan1']->getKey()] = 0;
    $data['payload']['menit'][$data['mapels']['pilihan2']->getKey()] = 601;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors([
            'menit.'.$data['mapels']['wajib2']->getKey(),
            'menit.'.$data['mapels']['pilihan1']->getKey(),
            'menit.'.$data['mapels']['pilihan2']->getKey(),
        ]);

    expect(PaketTryout::count())->toBe(0);
});

it('form edit mempertahankan batas waktu menit yang tersimpan', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $wajib1 = $data['mapels']['wajib1']->getKey();
    $data['payload']['menit'][$wajib1] = 90;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors();

    $paketTryout = PaketTryout::sole();

    $html = $this->actingAs($admin)->get(route('admin.paket-tryout.edit', $paketTryout))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<input[^>]*name="menit\['.$wajib1.'\]"[^>]*value="90"/');
});

it('batas waktu yang disunting admin tetap dihormati', function () {
    $admin = User::factory()->admin()->create();
    $payload = setupTryoutF4()['payload'];
    $payload['menit'] = array_fill_keys(array_keys($payload['menit']), 90);

    $this->actingAs($admin)->post('/admin/paket-tryout', $payload)
        ->assertRedirect(route('admin.paket-tryout.index'));

    $tersimpan = PaketTryout::sole()->daftarMapel->pluck('menit');

    expect($tersimpan->unique()->values()->all())->toBe([90]);
});

it('validasi paket tryout: field wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/paket-tryout', [])
        ->assertSessionHasErrors([
            'nama_paket',
            'tingkat',
            'paket_soal',
            'menit',
        ]);
});

it('tiap mapel tampil tepat sekali pada form paket tryout', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $html = $this->actingAs($admin)->get('/admin/paket-tryout/create')
        ->assertOk()
        ->getContent();

    foreach ($data['mapels'] as $mapel) {
        expect(substr_count($html, 'name="paket_soal['.$mapel->getKey().']"'))->toBe(1);
    }
});

it('menyunting ulang paket tryout tidak menggandakan baris mapelnya', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors();

    $paketTryout = PaketTryout::sole();

    $this->actingAs($admin)->put(route('admin.paket-tryout.update', $paketTryout), $data['payload'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));

    $tersimpan = isiPaketTryoutAdmin($paketTryout);

    expect($tersimpan)->toHaveCount(5);

    foreach (['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'] as $slot) {
        expect($tersimpan->get($data['mapels'][$slot]->getKey()))
            ->toBe($data['pakets'][$slot]->getKey());
    }
});

it('baris mapel yang tidak lagi ditawarkan ikut dibuang saat disunting', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors();

    $paketTryout = PaketTryout::sole();

    // Mapel berpindah sasaran ke SD, sehingga berhenti ditawarkan form dan
    // barisnya harus ikut terbuang, bukan ditinggal sebagai sisa.
    $dibuang = $data['mapels']['wajib3'];
    $dibuang->update(['tingkat' => Mapel::TINGKAT_SD]);
    unset($data['payload']['paket_soal'][$dibuang->getKey()]);

    $this->actingAs($admin)->put(route('admin.paket-tryout.update', $paketTryout), $data['payload'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));

    $tersimpan = isiPaketTryoutAdmin($paketTryout);

    expect($tersimpan)->toHaveCount(4)
        ->and($tersimpan->has($dibuang->getKey()))->toBeFalse();
});

it('tryout SMK tanpa pilihan kejuruan/PKK ditolak', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMK', false);

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['paket_soal']);
});

it('tryout SMK dengan dua pilihan kejuruan sah', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMK', true);
    $pilihan2Kejuruan = Mapel::factory()
        ->state(['jenis' => Mapel::JENIS_PILIHAN_KEJURUAN])
        ->create(['tingkat' => 'SMK']);
    $paket2Kejuruan = paketSoalF4($pilihan2Kejuruan);
    $data['payload']['paket_soal'][$pilihan2Kejuruan->getKey()] = $paket2Kejuruan->getKey();
    $data['payload']['menit'][$pilihan2Kejuruan->getKey()] = 60;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));
});

it('tryout SMA bebas memilih pilihan umum tanpa kejuruan', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMA', false);

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'nama_paket' => $data['payload']['nama_paket'],
        'tingkat' => 'SMA',
    ]);
});

it('tryout SMK dapat memakai mapel ber-tingkat SMA (satu arah)', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMK', true, 'SMA');

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'nama_paket' => $data['payload']['nama_paket'],
        'tingkat' => 'SMK',
    ]);

    expect(isiPaketTryoutAdmin(PaketTryout::sole())->get($data['mapels']['wajib1']->getKey()))
        ->toBe($data['pakets']['wajib1']->getKey());
});

it('mapel ber-tingkat SMK ditolak pada tryout SMA', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMA', false, 'SMK');

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors([
            'paket_soal.'.$data['mapels']['wajib1']->getKey(),
        ]);
});

it('mapel ber-tingkat all dapat dipakai pada tryout SMK', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMK', true, 'all');

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'nama_paket' => $data['payload']['nama_paket'],
        'tingkat' => 'SMK',
    ]);
});

it('menolak paket soal yang sudah tidak ada lagi', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $dihapus = paketSoalF4($data['mapels']['pilihan2']);
    $dihapus->soal()->detach();
    $dihapus->delete();

    $data['payload']['paket_soal'][$data['mapels']['pilihan2']->getKey()] = $dihapus->getKey();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors([
            'paket_soal.'.$data['mapels']['pilihan2']->getKey(),
        ]);

    expect(PaketTryout::count())->toBe(0);
});

it('paket soal tanpa soal ditolak (minimal 1 soal per mapel)', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $paketKosong = PaketSoal::factory()->create(['mapel_id' => $data['mapels']['pilihan2']->id]);
    $data['payload']['paket_soal'][$data['mapels']['pilihan2']->getKey()] = $paketKosong->getKey();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors([
            'paket_soal.'.$data['mapels']['pilihan2']->getKey(),
        ]);
});

it('validasi berlaku juga saat mengedit paket tryout', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors();

    $paketTryout = PaketTryout::sole();
    $payload = $data['payload'];
    $payload['nama_paket'] = 'Tidak boleh tersimpan';
    unset($payload['paket_soal'][$data['mapels']['wajib3']->getKey()]);

    $this->actingAs($admin)->put(route('admin.paket-tryout.update', $paketTryout), $payload)
        ->assertSessionHasErrors(['paket_soal.'.$data['mapels']['wajib3']->getKey()]);

    $this->assertDatabaseHas('paket_tryout', [
        'id' => $paketTryout->id,
        'nama_paket' => $data['payload']['nama_paket'],
    ]);
});

it('admin dapat mengedit paket tryout yang valid', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $paketTryout = PaketTryout::first();
    $payload = $data['payload'];
    $payload['nama_paket'] = 'Tryout Revisi 2026';

    $this->actingAs($admin)->put("/admin/paket-tryout/{$paketTryout->id}", $payload)
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'id' => $paketTryout->id,
        'nama_paket' => 'Tryout Revisi 2026',
    ]);
});

it('paket tryout yang belum dipakai dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $paketTryout = PaketTryout::factory()->create();

    $this->actingAs($admin)->delete("/admin/paket-tryout/{$paketTryout->id}")
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertSoftDeleted('paket_tryout', ['id' => $paketTryout->id]);
});

it('paket tryout dengan percobaan diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $paketTryout = PaketTryout::factory()->create();
    Percobaan::factory()->tryout()->create(['paket_tryout_id' => $paketTryout->id]);

    $this->actingAs($admin)->delete("/admin/paket-tryout/{$paketTryout->id}")
        ->assertRedirect(route('admin.paket-tryout.index'))
        ->assertSessionHas('error', 'Paket tryout masih memiliki riwayat pengerjaan, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('paket_tryout', ['id' => $paketTryout->id]);
});

it('mereset seluruh riwayat peserta atas paket tryout supaya paketnya bisa dihapus', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create();
    $paketTryout = PaketTryout::factory()->create();

    // Riwayat dibangun seperti hasil menutup tryout: satu percobaan selesai
    // beserta jawabannya, satu baris hasil, dan angka tracking yang
    // diturunkan dari keduanya.
    $mapel = $paketTryout->daftarMapel()->firstOrFail()->mapel;
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->getKey()]);
    $soal = Soal::factory()->create(['kompetensi_dasar_id' => $kd->getKey()]);

    $percobaan = Percobaan::factory()->tryout()->selesai()->create([
        'user_id' => $peserta->getKey(),
        'paket_tryout_id' => $paketTryout->getKey(),
        'daftar_soal' => [['mapel_id' => $mapel->getKey(), 'soal_ids' => [$soal->getKey()]]],
    ]);

    RiwayatPengerjaan::factory()->create([
        'user_id' => $peserta->getKey(),
        'percobaan_id' => $percobaan->getKey(),
        'soal_id' => $soal->getKey(),
        'paket_tryout_id' => $paketTryout->getKey(),
        'mode' => 'tryout',
    ]);

    HasilTryout::factory()->create([
        'user_id' => $peserta->getKey(),
        'paket_tryout_id' => $paketTryout->getKey(),
    ]);

    TrackingMapel::query()->create([
        'user_id' => $peserta->getKey(),
        'mapel_id' => $mapel->getKey(),
        'theta_estimasi' => 1.250,
        'level_kompetensi' => KompetensiLevel::MAHIR,
        'total_tryout_diikuti' => 1,
        'rata_rata_skor_irt' => 0.800,
        'last_updated' => now(),
    ]);

    TrackingKompetensi::query()->create([
        'user_id' => $peserta->getKey(),
        'kompetensi_dasar_id' => $kd->getKey(),
        'total_soal_dikerjakan' => 4,
        'total_benar' => 3,
        'persentase_benar' => 75.0,
        'theta_estimasi' => 1.250,
        'last_updated' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/paket-tryout')
        ->assertOk()
        ->assertSee('Reset Riwayat');

    $this->actingAs($admin)
        ->post("/admin/paket-tryout/{$paketTryout->getKey()}/reset-riwayat")
        ->assertRedirect(route('admin.paket-tryout.index'))
        ->assertSessionHas('success');

    expect(Percobaan::count())->toBe(0)
        ->and(RiwayatPengerjaan::count())->toBe(0)
        ->and(HasilTryout::count())->toBe(0);

    // Tracking ikut dihitung ulang dari riwayat yang tersisa, bukan ditinggal
    // membawa angka percobaan yang sudah dihapus (S2).
    $barisMapel = TrackingMapel::query()->where('user_id', $peserta->getKey())->sole();
    expect($barisMapel->total_tryout_diikuti)->toBe(0)
        ->and($barisMapel->theta_estimasi)->toBeNull()
        ->and($barisMapel->rata_rata_skor_irt)->toBeNull()
        ->and($barisMapel->level_kompetensi)->toBe(KompetensiLevel::BELUM_TERIDENTIFIKASI);

    $barisKd = TrackingKompetensi::query()->where('user_id', $peserta->getKey())->sole();
    expect($barisKd->total_soal_dikerjakan)->toBe(0)
        ->and($barisKd->theta_estimasi)->toBeNull();

    $this->actingAs($admin)
        ->delete("/admin/paket-tryout/{$paketTryout->getKey()}")
        ->assertRedirect(route('admin.paket-tryout.index'))
        ->assertSessionHas('success', 'Paket tryout berhasil dihapus.');

    $this->assertSoftDeleted('paket_tryout', ['id' => $paketTryout->getKey()]);
});

it('tidak menawarkan reset riwayat pada paket yang belum pernah dikerjakan', function () {
    $admin = User::factory()->admin()->create();
    $paketTryout = PaketTryout::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/paket-tryout')
        ->assertOk()
        ->assertDontSee('Reset Riwayat');

    // Dilewati pun, tombolnya tidak menghapus apa pun dan tidak menyesatkan.
    $this->actingAs($admin)
        ->post("/admin/paket-tryout/{$paketTryout->getKey()}/reset-riwayat")
        ->assertRedirect(route('admin.paket-tryout.index'))
        ->assertSessionHas('success', 'Paket tryout tidak memiliki riwayat pengerjaan.');

    $this->assertNotSoftDeleted('paket_tryout', ['id' => $paketTryout->getKey()]);
});

it('peserta tidak dapat mereset riwayat paket tryout (403)', function () {
    $peserta = User::factory()->peserta()->create();
    $paketTryout = PaketTryout::factory()->create();

    $this->actingAs($peserta)
        ->post("/admin/paket-tryout/{$paketTryout->getKey()}/reset-riwayat")
        ->assertForbidden();
});

it('form paket tryout menampilkan tingkat sebagai keterangan, bukan pilihan', function () {
    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get('/admin/paket-tryout/create')
        ->assertOk()
        ->assertSee('SMA/SMK/Sederajat')
        ->getContent();

    expect($html)
        ->toMatch('/type="hidden"[^>]*name="tingkat"[^>]*value="SMK"/')
        ->not->toMatch('/<select[^>]*name="tingkat"/');
});

it('mengedit paket tryout mempertahankan tingkat SMA yang tersimpan', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMA', false);

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $paketTryout = PaketTryout::where('nama_paket', $data['payload']['nama_paket'])->sole();

    $html = $this->actingAs($admin)->get(route('admin.paket-tryout.edit', $paketTryout))
        ->assertOk()
        ->assertSee('SMA/SMK/Sederajat')
        ->getContent();

    expect($html)->toMatch('/type="hidden"[^>]*name="tingkat"[^>]*value="SMA"/');

    $this->actingAs($admin)->put(route('admin.paket-tryout.update', $paketTryout), $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'id' => $paketTryout->getKey(),
        'tingkat' => 'SMA',
    ]);
});

it('index paket tryout menampilkan tingkat sebagai SMA/SMK/Sederajat', function () {
    $admin = User::factory()->admin()->create();
    PaketTryout::factory()->create(['tingkat' => PaketTryout::TINGKAT_SMA]);

    $this->actingAs($admin)->get('/admin/paket-tryout')
        ->assertOk()
        ->assertSee('SMA/SMK/Sederajat');
});

it('index paket tryout menampilkan waktu per mapel, bukan satu batas waktu tryout', function () {
    $admin = User::factory()->admin()->create();
    PaketTryout::factory()->create();

    $this->actingAs($admin)->get('/admin/paket-tryout')
        ->assertOk()
        ->assertSee('75 menit wajib · 60 menit pilihan')
        ->assertDontSee('120 menit');
});

it('form paket tryout tidak menawarkan mapel ber-tingkat SD atau SMP', function () {
    $admin = User::factory()->admin()->create();
    $sd = Mapel::factory()->create(['tingkat' => Mapel::TINGKAT_SD]);
    $smp = Mapel::factory()->create(['tingkat' => Mapel::TINGKAT_SMP]);
    $smk = Mapel::factory()->create(['tingkat' => Mapel::TINGKAT_SMK]);

    $tertawakan = function ($mapels) use ($sd, $smp, $smk) {
        $ids = $mapels->pluck('id');

        return ! $ids->contains($sd->id)
            && ! $ids->contains($smp->id)
            && $ids->contains($smk->id);
    };

    $this->actingAs($admin)->get('/admin/paket-tryout/create')
        ->assertOk()
        ->assertViewHas('mapels', $tertawakan);

    $paketTryout = PaketTryout::factory()->create();

    $this->actingAs($admin)->get(route('admin.paket-tryout.edit', $paketTryout))
        ->assertOk()
        ->assertViewHas('mapels', $tertawakan);
});

/**
 * Posisi `selected` pada opsi paket soal — dicari di dalam `<select>`
 * milik mapel itu lebih dulu, supaya opsi paket milik mapel lain tidak
 * ikut terbaca.
 */
function opsiPaketTerpilih(string $html, int $paketId, int $mapelId): bool
{
    if (preg_match('/<select\b[^>]*name="paket_soal\['.$mapelId.'\]".*?<\/select>/s', $html, $blok) !== 1) {
        return false;
    }

    return preg_match(
        '/<option\b[^>]*\bvalue="'.$paketId.'"[^>]*\bselected\b/',
        $blok[0]
    ) === 1;
}

/** Urutan opsi paket soal pada HTML, dipakai untuk memastikan yang terbaru didahulukan. */
function urutOpsiPaket(string $html, int ...$paketIds): array
{
    $posisi = [];
    foreach ($paketIds as $id) {
        $posisi[$id] = strpos($html, '<option value="'.$id.'"');
    }

    return $posisi;
}

it('paket soal default ke paket terbaru milik mapel yang dipilih slot itu', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['kode' => 'AAA', 'jenis' => Mapel::JENIS_WAJIB]);
    Mapel::factory()->create(['kode' => 'ZZZ', 'jenis' => Mapel::JENIS_WAJIB]);
    $lama = paketSoalF4($mapel);
    $baru = paketSoalF4($mapel);

    $html = $this->actingAs($admin)->get('/admin/paket-tryout/create')
        ->assertOk()
        ->getContent();

    expect(opsiPaketTerpilih($html, $baru->id, $mapel->id))->toBeTrue()
        ->and(opsiPaketTerpilih($html, $lama->id, $mapel->id))->toBeFalse();

    // Urutan menurun supaya keputusan bawaan baris jatuh ke paket terbaru.
    $urut = urutOpsiPaket($html, $lama->id, $baru->id);
    expect($urut[$baru->id])->toBeLessThan($urut[$lama->id]);
});

it('form edit mempertahankan paket soal yang tersimpan meski bukan yang terbaru', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertRedirect(route('admin.paket-tryout.index'));

    $paketTryout = PaketTryout::where('nama_paket', $data['payload']['nama_paket'])->sole();
    $mapelWajib1 = $data['mapels']['wajib1'];
    $tersimpan = $data['pakets']['wajib1'];
    $lebihBaru = paketSoalF4($mapelWajib1);

    $html = $this->actingAs($admin)->get(route('admin.paket-tryout.edit', $paketTryout))
        ->assertOk()
        ->getContent();

    expect(opsiPaketTerpilih($html, $tersimpan->id, $mapelWajib1->id))->toBeTrue()
        ->and(opsiPaketTerpilih($html, $lebihBaru->id, $mapelWajib1->id))->toBeFalse();
});

it('form paket tryout mengelompokkan mapel wajib dan pilihan tanpa dropdown mapel', function () {
    $admin = User::factory()->admin()->create();

    $dibuat = [];

    foreach ([
        ['WJA', Mapel::JENIS_WAJIB, Mapel::TINGKAT_SMA],
        ['WJB', Mapel::JENIS_WAJIB, Mapel::TINGKAT_SMA],
        ['PSA', Mapel::JENIS_PILIHAN_UMUM, Mapel::TINGKAT_SMA],
        ['PSK', Mapel::JENIS_PILIHAN_UMUM, Mapel::TINGKAT_SMK],
    ] as [$kode, $jenis, $tingkat]) {
        $dibuat[] = Mapel::factory()->create([
            'kode' => $kode,
            'jenis' => $jenis,
            'tingkat' => $tingkat,
            'is_pkk' => false,
        ]);
    }

    // Tiap mapel punya daftar paket soal miliknya sendiri; tanpa itu barisnya
    // justru tampil sebagai keterangan dan tidak punya dropdown.
    foreach ($dibuat as $mapel) {
        paketSoalF4($mapel);
    }

    $html = $this->actingAs($admin)->get('/admin/paket-tryout/create')
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toMatch('/<select[^>]*name="mapel_/')
        ->not->toMatch('/name="mapel_wajib_/')
        ->and($html)->toContain('Mapel wajib')
        ->and($html)->toContain('Mapel pilihan · SMA')
        ->and($html)->toContain('Mapel pilihan · SMK');

    // Tiap mapel punya daftar paket soal miliknya sendiri, bukan satu daftar
    // bersama yang disaring menurut mapel terpilih.
    foreach (Mapel::query()->whereNull('deleted_at')->whereNotIn('tingkat', [Mapel::TINGKAT_SD, Mapel::TINGKAT_SMP])->pluck('id') as $mapelId) {
        expect($html)->toMatch('/<select[^>]*name="paket_soal\['.$mapelId.'\]"/');
    }
});

it('menyimpan satu paket soal untuk setiap mapel yang ditawarkan', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.paket-tryout.index'));

    $tersimpan = isiPaketTryoutAdmin(PaketTryout::sole());

    expect($tersimpan)->toHaveCount(5);

    foreach (['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'] as $slot) {
        expect($tersimpan->get($data['mapels'][$slot]->getKey()))
            ->toBe($data['pakets'][$slot]->getKey());
    }
});

it('menolak penyimpanan ketika ada mapel berpaket soal yang belum dipilih', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    unset($data['payload']['paket_soal'][$data['mapels']['wajib2']->getKey()]);

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['paket_soal.'.$data['mapels']['wajib2']->getKey()]);

    expect(PaketTryout::count())->toBe(0);
});

it('menolak mapel yang tidak ditawarkan pada form', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $sd = Mapel::factory()->create(['tingkat' => Mapel::TINGKAT_SD]);
    $data['payload']['paket_soal'][$sd->getKey()] = paketSoalF4($sd)->getKey();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['paket_soal.'.$sd->getKey()]);

    expect(PaketTryout::count())->toBe(0);
});

it('paket soal pada satu baris harus milik mapel baris itu', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $mapel = $data['mapels']['pilihan2'];
    $data['payload']['paket_soal'][$mapel->getKey()] = $data['pakets']['wajib1']->getKey();

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['paket_soal.'.$mapel->getKey()]);
});
