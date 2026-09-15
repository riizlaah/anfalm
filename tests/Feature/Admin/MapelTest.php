<?php

use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\User;

it('tamu yang membuka /admin/mapel dialihkan ke login', function () {
    $this->get('/admin/mapel')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin/mapel (403)', function () {
    $user = User::factory()->peserta()->create();

    $this->actingAs($user)->get('/admin/mapel')->assertForbidden();
});

it('admin dapat membuka halaman index mapel', function () {
    $admin = User::factory()->admin()->create();
    Mapel::factory()->create(['nama' => 'Matematika Wajib']);

    $this->actingAs($admin)->get('/admin/mapel')
        ->assertOk()
        ->assertSee('Manajemen Mapel')
        ->assertSee('Matematika Wajib');
});

it('admin dapat membuat mapel baru', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/mapel', [
        'kode' => 'MTK',
        'nama' => 'Matematika Wajib',
        'tingkat' => 'SMA',
        'jenis' => Mapel::JENIS_WAJIB,
        'is_pkk' => false,
    ])->assertRedirect(route('admin.mapel.index'));

    $this->assertDatabaseHas('mapel', [
        'kode' => 'MTK',
        'nama' => 'Matematika Wajib',
        'tingkat' => 'SMA',
        'jenis' => 'wajib',
        'is_pkk' => false,
    ]);
});

it('validasi mapel: kode, nama, tingkat dan jenis wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/mapel', [])
        ->assertSessionHasErrors(['kode', 'nama', 'tingkat', 'jenis']);
});

it('validasi mapel: kode harus unik', function () {
    $admin = User::factory()->admin()->create();
    Mapel::factory()->create(['kode' => 'MTK']);

    $this->actingAs($admin)->post('/admin/mapel', [
        'kode' => 'MTK',
        'nama' => 'Matematika',
        'tingkat' => 'SMA',
        'jenis' => Mapel::JENIS_WAJIB,
    ])->assertSessionHasErrors(['kode']);
});

it('admin dapat mengedit mapel', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['kode' => 'MTK']);

    $this->actingAs($admin)->put("/admin/mapel/{$mapel->id}", [
        'kode' => 'MTK',
        'nama' => 'Matematika Peminatan',
        'tingkat' => 'SMA',
        'jenis' => Mapel::JENIS_PILIHAN_UMUM,
        'is_pkk' => false,
    ])->assertRedirect(route('admin.mapel.index'));

    $this->assertDatabaseHas('mapel', [
        'id' => $mapel->id,
        'nama' => 'Matematika Peminatan',
        'jenis' => 'pilihan_umum',
    ]);
});

it('validasi kode unik pada update mengabaikan dirinya sendiri', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->put("/admin/mapel/{$mapel->id}", [
        'kode' => $mapel->kode,
        'nama' => 'Nama Baru',
        'tingkat' => $mapel->tingkat,
        'jenis' => $mapel->jenis,
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.mapel.index'));
});

it('is_pkk dapat disimpan saat membuat mapel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/mapel', [
        'kode' => 'PKK',
        'nama' => 'Proyek Kreatif & Kewirausahaan',
        'tingkat' => 'SMK',
        'jenis' => Mapel::JENIS_PILIHAN_KEJURUAN,
        'is_pkk' => true,
    ])->assertRedirect(route('admin.mapel.index'));

    $this->assertDatabaseHas('mapel', ['kode' => 'PKK', 'is_pkk' => true]);
});

it('mapel yang belum dipakai dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Ekonomi']);

    $this->actingAs($admin)->delete("/admin/mapel/{$mapel->id}")
        ->assertRedirect(route('admin.mapel.index'));

    $this->assertSoftDeleted('mapel', ['id' => $mapel->id]);
});

it('mapel yang telah dihapus tidak tampil pada index', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Sosiologi']);

    $this->actingAs($admin)->delete("/admin/mapel/{$mapel->id}");

    $this->actingAs($admin)->get('/admin/mapel')
        ->assertOk()
        ->assertDontSee('Sosiologi');
});

it('mapel yang sudah dipakai (memiliki KD) diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->delete("/admin/mapel/{$mapel->id}")
        ->assertRedirect(route('admin.mapel.index'))
        ->assertSessionHas('error', 'Mapel masih digunakan oleh soal/paket, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('mapel', ['id' => $mapel->id]);
});
