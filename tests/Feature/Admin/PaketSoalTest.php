<?php

use App\Models\DetailPaketSoal;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\User;

it('tamu yang membuka /admin/paket-soal dialihkan ke login', function () {
    $this->get('/admin/paket-soal')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin/paket-soal (403)', function () {
    $user = User::factory()->peserta()->create();

    $this->actingAs($user)->get('/admin/paket-soal')->assertForbidden();
});

it('admin dapat membuka halaman index paket soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika Wajib']);
    PaketSoal::factory()->create(['nama_paket' => 'Paket Matematika 1', 'mapel_id' => $mapel->id]);

    $this->actingAs($admin)->get('/admin/paket-soal')
        ->assertOk()
        ->assertSee('Manajemen Paket Soal')
        ->assertSee('Paket Matematika 1')
        ->assertSee('Matematika Wajib');
});

it('admin dapat membuat paket soal beserta detail soalnya', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $soal = mapelSoalF4($mapel);

    $this->actingAs($admin)->post('/admin/paket-soal', [
        'nama_paket' => 'Paket Matematika 1',
        'deskripsi' => 'Latihan intensif',
        'mapel_id' => $mapel->id,
        'soal_ids' => [$soal->id],
    ])->assertRedirect(route('admin.paket-soal.index'));

    $this->assertDatabaseHas('paket_soal', [
        'nama_paket' => 'Paket Matematika 1',
        'deskripsi' => 'Latihan intensif',
        'mapel_id' => $mapel->id,
    ]);
    $this->assertDatabaseHas('detail_paket_soal', [
        'soal_id' => $soal->id,
    ]);
});

it('validasi paket soal: nama, mapel dan minimal satu soal wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/paket-soal', [])
        ->assertSessionHasErrors(['nama_paket', 'mapel_id', 'soal_ids']);
});

it('validasi paket soal: soal tidak boleh duplikat dalam satu paket', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $soal = mapelSoalF4($mapel);

    $this->actingAs($admin)->post('/admin/paket-soal', [
        'nama_paket' => 'Paket Duplikat',
        'mapel_id' => $mapel->id,
        'soal_ids' => [$soal->id, $soal->id],
    ])->assertSessionHasErrors(['soal_ids']);
});

it('validasi paket soal: soal harus berasal dari mapel yang sama', function () {
    $admin = User::factory()->admin()->create();
    $mapelPaket = Mapel::factory()->create();
    $soalMapelLain = mapelSoalF4(Mapel::factory()->create());

    $this->actingAs($admin)->post('/admin/paket-soal', [
        'nama_paket' => 'Paket Tidak Valid',
        'mapel_id' => $mapelPaket->id,
        'soal_ids' => [$soalMapelLain->id],
    ])->assertSessionHasErrors(['soal_ids']);
});

it('admin dapat mengedit paket soal termasuk merubah daftar soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $soalLama = mapelSoalF4($mapel);
    $soalBaru = mapelSoalF4($mapel);
    $paket = PaketSoal::factory()->create(['mapel_id' => $mapel->id]);
    DetailPaketSoal::create(['paket_soal_id' => $paket->id, 'soal_id' => $soalLama->id]);

    $this->actingAs($admin)->put("/admin/paket-soal/{$paket->id}", [
        'nama_paket' => 'Paket Matematika Revisi',
        'deskripsi' => null,
        'mapel_id' => $mapel->id,
        'soal_ids' => [$soalLama->id, $soalBaru->id],
    ])->assertRedirect(route('admin.paket-soal.index'));

    $this->assertDatabaseHas('paket_soal', [
        'id' => $paket->id,
        'nama_paket' => 'Paket Matematika Revisi',
    ]);
    $this->assertDatabaseCount('detail_paket_soal', 2);
});

it('paket soal yang belum dipakai dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $paket = paketSoalF4(Mapel::factory()->create());

    $this->actingAs($admin)->delete("/admin/paket-soal/{$paket->id}")
        ->assertRedirect(route('admin.paket-soal.index'));

    $this->assertSoftDeleted('paket_soal', ['id' => $paket->id]);
});

it('paket soal yang dipakai tryout diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $paket = paketSoalF4(Mapel::factory()->create());
    PaketTryout::factory()->create(['paket_soal_wajib_1_id' => $paket->id]);

    $this->actingAs($admin)->delete("/admin/paket-soal/{$paket->id}")
        ->assertRedirect(route('admin.paket-soal.index'))
        ->assertSessionHas('error', 'Paket soal masih digunakan oleh tryout, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('paket_soal', ['id' => $paket->id]);
});

it('soal yang sudah dimasukkan ke paket ditandai di halaman create', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Bahasa Indonesia']);
    mapelSoalF4($mapel);

    $this->actingAs($admin)->get('/admin/paket-soal/create')
        ->assertOk()
        ->assertSee('Tambah Paket Soal')
        ->assertSee('Bahasa Indonesia');
});
