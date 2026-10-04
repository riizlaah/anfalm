<?php

use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
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

    foreach (['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'] as $slot) {
        $pakets[$slot] = paketSoalF4($mapels[$slot]);
        $paketPerMapel[$mapels[$slot]->getKey()] = $pakets[$slot]->getKey();
    }

    $payload = [
        'nama_paket' => 'Tryout '.$tingkat.' '.fake()->word(),
        'deskripsi' => null,
        'tingkat' => $tingkat,
        'batas_waktu_menit' => 120,
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
        'batas_waktu_menit' => 120,
    ]);

    expect(PaketTryout::sole()->daftarMapel()->count())->toBe(5);
});

it('batas waktu default 120 menit saat dikosongkan', function () {
    $admin = User::factory()->admin()->create();
    $payload = setupTryoutF4()['payload'];
    $payload['batas_waktu_menit'] = null;

    $this->actingAs($admin)->post('/admin/paket-tryout', $payload)
        ->assertRedirect(route('admin.paket-tryout.index'));

    $this->assertDatabaseHas('paket_tryout', [
        'nama_paket' => $payload['nama_paket'],
        'batas_waktu_menit' => 120,
    ]);
});

it('batas waktu yang diisi admin tetap dihormati', function () {
    $admin = User::factory()->admin()->create();
    $payload = setupTryoutF4()['payload'];
    $payload['batas_waktu_menit'] = 90;

    $this->actingAs($admin)->post('/admin/paket-tryout', $payload);

    $this->assertDatabaseHas('paket_tryout', [
        'nama_paket' => $payload['nama_paket'],
        'batas_waktu_menit' => 90,
    ]);
});

it('validasi paket tryout: field wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/paket-tryout', [])
        ->assertSessionHasErrors([
            'nama_paket',
            'tingkat',
            'paket_soal',
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
