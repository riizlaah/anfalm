<?php

use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\User;

/**
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
    foreach (['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'] as $slot) {
        $pakets[$slot] = paketSoalF4($mapels[$slot]);
    }

    $slotMapel = [
        'wajib1' => 'mapel_wajib_1',
        'wajib2' => 'mapel_wajib_2',
        'wajib3' => 'mapel_wajib_3',
        'pilihan1' => 'mapel_pilihan_1',
        'pilihan2' => 'mapel_pilihan_2',
    ];
    $slotPaket = [
        'wajib1' => 'paket_soal_wajib_1_id',
        'wajib2' => 'paket_soal_wajib_2_id',
        'wajib3' => 'paket_soal_wajib_3_id',
        'pilihan1' => 'paket_soal_pilihan_1_id',
        'pilihan2' => 'paket_soal_pilihan_2_id',
    ];

    $payload = [
        'nama_paket' => 'Tryout '.$tingkat.' '.fake()->word(),
        'deskripsi' => null,
        'tingkat' => $tingkat,
        'batas_waktu_menit' => 120,
    ];
    foreach ($slotMapel as $slot => $field) {
        $payload[$field] = $mapels[$slot]->id;
        $payload[$slotPaket[$slot]] = $pakets[$slot]->id;
    }

    return ['payload' => $payload, 'mapels' => $mapels, 'pakets' => $pakets];
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
        'mapel_wajib_1' => $data['payload']['mapel_wajib_1'],
        'mapel_pilihan_1' => $data['payload']['mapel_pilihan_1'],
    ]);
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
            'mapel_wajib_1',
            'mapel_wajib_2',
            'mapel_wajib_3',
            'mapel_pilihan_1',
            'mapel_pilihan_2',
            'paket_soal_wajib_1_id',
            'paket_soal_wajib_2_id',
            'paket_soal_wajib_3_id',
            'paket_soal_pilihan_1_id',
            'paket_soal_pilihan_2_id',
        ]);
});

it('mapel pilihan tidak boleh sama dengan mapel wajib', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $data['payload']['mapel_pilihan_1'] = $data['mapels']['wajib3']->id;
    $data['payload']['paket_soal_pilihan_1_id'] = $data['pakets']['wajib3']->id;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['mapel_pilihan_1']);
});

it('mapel pilihan 1 dan 2 tidak boleh sama', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $data['payload']['mapel_pilihan_2'] = $data['mapels']['pilihan1']->id;
    $data['payload']['paket_soal_pilihan_2_id'] = $data['pakets']['pilihan1']->id;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['mapel_pilihan_2']);
});

it('mapel wajib tidak boleh berduplikasi', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $data['payload']['mapel_wajib_2'] = $data['mapels']['wajib1']->id;
    $data['payload']['paket_soal_wajib_2_id'] = $data['pakets']['wajib1']->id;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['mapel_wajib_2']);
});

it('tryout SMK tanpa pilihan kejuruan/PKK ditolak', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMK', false);

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['mapel_pilihan_1']);
});

it('tryout SMK dengan dua pilihan kejuruan sah', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMK', true);
    $pilihan2Kejuruan = Mapel::factory()
        ->state(['jenis' => Mapel::JENIS_PILIHAN_KEJURUAN])
        ->create(['tingkat' => 'SMK']);
    $paket2Kejuruan = paketSoalF4($pilihan2Kejuruan);
    $data['payload']['mapel_pilihan_2'] = $pilihan2Kejuruan->id;
    $data['payload']['paket_soal_pilihan_2_id'] = $paket2Kejuruan->id;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
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
        'mapel_wajib_1' => $data['payload']['mapel_wajib_1'],
    ]);
});

it('mapel ber-tingkat SMK ditolak pada tryout SMA', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4('SMA', false, 'SMK');

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['mapel_wajib_1']);
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

it('paket soal pada slot harus berasal dari mapel yang sama dengan slot', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $data['payload']['paket_soal_pilihan_1_id'] = $data['pakets']['wajib1']->id;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['paket_soal_pilihan_1_id']);
});

it('paket soal tanpa soal ditolak (minimal 1 soal per mapel)', function () {
    $admin = User::factory()->admin()->create();
    $data = setupTryoutF4();
    $paketKosong = PaketSoal::factory()->create(['mapel_id' => $data['mapels']['pilihan2']->id]);
    $data['payload']['paket_soal_pilihan_2_id'] = $paketKosong->id;

    $this->actingAs($admin)->post('/admin/paket-tryout', $data['payload'])
        ->assertSessionHasErrors(['paket_soal_pilihan_2_id']);
});

it('validasi berlaku juga saat mengedit paket tryout', function () {
    $admin = User::factory()->admin()->create();
    $existing = PaketTryout::factory()->create();
    $data = setupTryoutF4('SMK', false);

    $this->actingAs($admin)->put("/admin/paket-tryout/{$existing->id}", $data['payload'])
        ->assertSessionHasErrors(['mapel_pilihan_1']);

    $this->assertDatabaseHas('paket_tryout', [
        'id' => $existing->id,
        'nama_paket' => $existing->nama_paket,
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
