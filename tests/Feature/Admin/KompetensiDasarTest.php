<?php

use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\Soal;
use App\Models\User;

it('tamu yang membuka /admin/kompetensi-dasar dialihkan ke login', function () {
    $this->get('/admin/kompetensi-dasar')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin/kompetensi-dasar (403)', function () {
    $user = User::factory()->peserta()->create();

    $this->actingAs($user)->get('/admin/kompetensi-dasar')->assertForbidden();
});

it('admin dapat membuka halaman index kompetensi dasar', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika']);
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'deskripsi' => 'KD ini terlihat']);

    $this->actingAs($admin)->get('/admin/kompetensi-dasar')
        ->assertOk()
        ->assertSee('Manajemen Kompetensi Dasar')
        ->assertSee('Matematika')
        ->assertSee('KD ini terlihat');
});

it('admin dapat membuat kompetensi dasar baru', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->post('/admin/kompetensi-dasar', [
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => '3.7',
        'deskripsi' => 'Menganalisis fungsi irasional.',
        'materi_pokok' => 'Fungsi irasional',
        'level_kognitif' => 'penerapan',
        'batasan' => 'hanya pangkat bulat positif',
    ])->assertRedirect(route('admin.kompetensi-dasar.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('kompetensi_dasar', [
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => '3.7',
        'deskripsi' => 'Menganalisis fungsi irasional.',
        'level_kognitif' => 'penerapan',
    ]);
});

it('validasi KD: mapel, kode, deskripsi dan level wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/kompetensi-dasar', [])
        ->assertSessionHasErrors(['mapel_id', 'kode_kompetensi', 'deskripsi', 'level_kognitif']);
});

it('validasi KD: kode_kompetensi harus unik dalam mapel yang sama', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->post('/admin/kompetensi-dasar', [
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => '3.1',
        'deskripsi' => 'Kode duplikat',
        'level_kognitif' => 'pengetahuan',
    ])->assertSessionHasErrors(['kode_kompetensi']);
});

it('kode_kompetensi yang sama diperbolehkan pada mapel berbeda', function () {
    $admin = User::factory()->admin()->create();
    $mapelA = Mapel::factory()->create();
    $mapelB = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapelA->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->post('/admin/kompetensi-dasar', [
        'mapel_id' => $mapelB->id,
        'kode_kompetensi' => '3.1',
        'deskripsi' => 'Kode sama, mapel beda',
        'level_kognitif' => 'pengetahuan',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('kompetensi_dasar', [
        'mapel_id' => $mapelB->id,
        'kode_kompetensi' => '3.1',
    ]);
});

it('admin dapat mengedit kompetensi dasar', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->put("/admin/kompetensi-dasar/{$kd->id}", [
        'mapel_id' => $kd->mapel_id,
        'kode_kompetensi' => $kd->kode_kompetensi,
        'deskripsi' => 'Deskripsi diperbarui',
        'level_kognitif' => 'penalaran',
    ])->assertRedirect(route('admin.kompetensi-dasar.index'));

    $this->assertDatabaseHas('kompetensi_dasar', [
        'id' => $kd->id,
        'deskripsi' => 'Deskripsi diperbarui',
        'level_kognitif' => 'penalaran',
    ]);
});

it('validasi kode_kompetensi unik pada update mengabaikan dirinya sendiri', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->put("/admin/kompetensi-dasar/{$kd->id}", [
        'mapel_id' => $kd->mapel_id,
        'kode_kompetensi' => $kd->kode_kompetensi,
        'deskripsi' => $kd->deskripsi,
        'level_kognitif' => $kd->level_kognitif,
    ])->assertSessionHasNoErrors();
});

it('KD yang belum dipakai dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->delete("/admin/kompetensi-dasar/{$kd->id}")
        ->assertRedirect(route('admin.kompetensi-dasar.index'));

    $this->assertSoftDeleted('kompetensi_dasar', ['id' => $kd->id]);
});

it('KD yang sudah dipakai (memiliki soal) diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();
    Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);

    $this->actingAs($admin)->delete("/admin/kompetensi-dasar/{$kd->id}")
        ->assertRedirect(route('admin.kompetensi-dasar.index'))
        ->assertSessionHas('error', 'Kompetensi dasar masih digunakan oleh soal, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kd->id]);
});
