<?php

use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\Soal;
use App\Models\User;

it('tamu yang membuka halaman KD sebuah mapel dialihkan ke login', function () {
    $mapel = Mapel::factory()->create();

    $this->get("/admin/mapel/{$mapel->id}/kompetensi-dasar")->assertRedirect('/login');
});

it('peserta tidak dapat membuka halaman KD sebuah mapel (403)', function () {
    $user = User::factory()->peserta()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($user)->get("/admin/mapel/{$mapel->id}/kompetensi-dasar")->assertForbidden();
});

it('admin dapat membuka halaman index kompetensi dasar', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika']);
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'deskripsi' => 'KD ini terlihat']);

    $this->actingAs($admin)->get("/admin/mapel/{$mapel->id}/kompetensi-dasar")
        ->assertOk()
        ->assertSee('Manajemen Kompetensi Dasar')
        ->assertSee('Matematika')
        ->assertSee('KD ini terlihat');
});

it('index KD hanya menampilkan KD milik mapel yang dipilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $mapelLain = Mapel::factory()->create();

    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'deskripsi' => 'KD milik mapel terpilih']);
    KompetensiDasar::factory()->create(['mapel_id' => $mapelLain->id, 'deskripsi' => 'KD milik mapel lain']);

    $this->actingAs($admin)->get("/admin/mapel/{$mapel->id}/kompetensi-dasar")
        ->assertOk()
        ->assertSee('KD milik mapel terpilih')
        ->assertDontSee('KD milik mapel lain');
});

it('index KD dapat difilter berdasarkan level kognitif', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'deskripsi' => 'KD gabungan', 'level_kognitif' => 'pengetahuan_dan_pemahaman']);
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'deskripsi' => 'KD penalaran', 'level_kognitif' => 'penalaran']);

    $this->actingAs($admin)->get("/admin/mapel/{$mapel->id}/kompetensi-dasar?level_kognitif=penalaran")
        ->assertOk()
        ->assertSee('KD penalaran')
        ->assertDontSee('KD gabungan');
});

it('admin dapat membuat kompetensi dasar baru', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar", [
        'kode_kompetensi' => '3.7',
        'deskripsi' => 'Menganalisis fungsi irasional.',
        'materi_pokok' => 'Fungsi irasional',
        'level_kognitif' => 'penerapan',
        'batasan' => 'hanya pangkat bulat positif',
    ])->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $mapel))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('kompetensi_dasar', [
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => '3.7',
        'deskripsi' => 'Menganalisis fungsi irasional.',
        'level_kognitif' => 'penerapan',
    ]);
});

it('validasi KD: kode, deskripsi dan level wajib', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar", [])
        ->assertSessionHasErrors(['kode_kompetensi', 'deskripsi', 'level_kognitif']);
});

it('validasi KD: level kognitif lama di luar aturan TKA ditolak', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    foreach (['pengetahuan', 'pemahaman'] as $levelLama) {
        $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar", [
            'kode_kompetensi' => '3.10',
            'deskripsi' => 'Level lama',
            'level_kognitif' => $levelLama,
        ])->assertSessionHasErrors(['level_kognitif']);
    }

    $this->assertDatabaseMissing('kompetensi_dasar', ['deskripsi' => 'Level lama']);
});

it('KD hanya menampilkan dan menawarkan tiga level TKA', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'level_kognitif' => 'pengetahuan_dan_pemahaman']);

    $this->actingAs($admin)->get("/admin/mapel/{$mapel->id}/kompetensi-dasar")
        ->assertOk()
        ->assertSee('Pengetahuan dan Pemahaman')
        ->assertDontSee('Pengetahuan_dan_pemahaman')
        ->assertDontSee('value="pengetahuan"', false)
        ->assertDontSee('value="pemahaman"', false);
});

it('gagal validasi membuka dialog kembali dengan isian yang sama dan tanpa old input', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar", [
        'kode_kompetensi' => '',
        'deskripsi' => 'Isian kandidat',
        'level_kognitif' => 'penalaran',
    ])
        ->assertSessionHasErrors(['kode_kompetensi'])
        ->assertSessionHas('open_dialog', 'create-kd')
        ->assertSessionHas('form_input', fn (array $input) => $input['deskripsi'] === 'Isian kandidat')
        ->assertSessionMissing('_old_input');
});

it('validasi KD: kode_kompetensi harus unik dalam mapel yang sama', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar", [
        'kode_kompetensi' => '3.1',
        'deskripsi' => 'Kode duplikat',
        'level_kognitif' => 'pengetahuan_dan_pemahaman',
    ])->assertSessionHasErrors(['kode_kompetensi']);
});

it('kode_kompetensi yang sama dengan KD terhapus dapat dibuat lagi di mapel yang sama', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);
    $kd->delete();

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar", [
        'kode_kompetensi' => '3.1',
        'deskripsi' => 'KD baru',
        'level_kognitif' => 'pengetahuan_dan_pemahaman',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $mapel));

    $this->assertDatabaseHas('kompetensi_dasar', [
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => '3.1',
        'deskripsi' => 'KD baru',
        'deleted_at' => null,
    ]);
});

it('kode_kompetensi yang sama diperbolehkan pada mapel berbeda', function () {
    $admin = User::factory()->admin()->create();
    $mapelA = Mapel::factory()->create();
    $mapelB = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapelA->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->post("/admin/mapel/{$mapelB->id}/kompetensi-dasar", [
        'kode_kompetensi' => '3.1',
        'deskripsi' => 'Kode sama, mapel beda',
        'level_kognitif' => 'pengetahuan_dan_pemahaman',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('kompetensi_dasar', [
        'mapel_id' => $mapelB->id,
        'kode_kompetensi' => '3.1',
    ]);
});

it('admin dapat mengedit kompetensi dasar', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->put("/admin/mapel/{$kd->mapel_id}/kompetensi-dasar/{$kd->id}", [
        'kode_kompetensi' => $kd->kode_kompetensi,
        'deskripsi' => 'Deskripsi diperbarui',
        'level_kognitif' => 'penalaran',
    ])->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $kd->mapel_id));

    $this->assertDatabaseHas('kompetensi_dasar', [
        'id' => $kd->id,
        'deskripsi' => 'Deskripsi diperbarui',
        'level_kognitif' => 'penalaran',
    ]);
});

it('validasi kode_kompetensi unik pada update mengabaikan dirinya sendiri', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->put("/admin/mapel/{$kd->mapel_id}/kompetensi-dasar/{$kd->id}", [
        'kode_kompetensi' => $kd->kode_kompetensi,
        'deskripsi' => $kd->deskripsi,
        'level_kognitif' => $kd->level_kognitif,
    ])->assertSessionHasNoErrors();
});

it('gagal validasi pada edit membuka dialog edit baris itu sendiri', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->put("/admin/mapel/{$kd->mapel_id}/kompetensi-dasar/{$kd->id}", [
        'kode_kompetensi' => $kd->kode_kompetensi,
        'deskripsi' => '',
        'level_kognitif' => $kd->level_kognitif,
    ])
        ->assertSessionHasErrors(['deskripsi'])
        ->assertSessionHas('open_dialog', "edit-kd-{$kd->id}")
        ->assertSessionMissing('_old_input');
});

it('KD dari mapel lain tidak dapat diedi lewat rute mapel ini (404)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdLain = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->put("/admin/mapel/{$mapel->id}/kompetensi-dasar/{$kdLain->id}", [
        'kode_kompetensi' => $kdLain->kode_kompetensi,
        'deskripsi' => 'Seharusnya tidak tersimpan',
        'level_kognitif' => 'penalaran',
    ])->assertNotFound();

    $this->assertDatabaseMissing('kompetensi_dasar', [
        'id' => $kdLain->id,
        'deskripsi' => 'Seharusnya tidak tersimpan',
    ]);
});

it('KD dari mapel lain tidak dapat dihapus lewat rute mapel ini (404)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdLain = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->delete("/admin/mapel/{$mapel->id}/kompetensi-dasar/{$kdLain->id}")
        ->assertNotFound();

    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kdLain->id]);
});

it('KD yang belum dipakai dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->delete("/admin/mapel/{$kd->mapel_id}/kompetensi-dasar/{$kd->id}")
        ->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $kd->mapel_id));

    $this->assertSoftDeleted('kompetensi_dasar', ['id' => $kd->id]);
});

it('KD yang sudah dipakai (memiliki soal) diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();
    Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);

    $this->actingAs($admin)->delete("/admin/mapel/{$kd->mapel_id}/kompetensi-dasar/{$kd->id}")
        ->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $kd->mapel_id))
        ->assertSessionHas('error', 'Kompetensi dasar masih digunakan oleh soal, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kd->id]);
});

it('bulk delete KD menghapus KD yang dipilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd1 = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $kd2 = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar/bulk-delete", [
        'ids' => [$kd1->id],
    ])->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $mapel))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('kompetensi_dasar', ['id' => $kd1->id]);
    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kd2->id]);
});

it('bulk delete KD dibatasi pada mapel di rute', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdMilikMapel = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $kdLain = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar/bulk-delete", [
        'ids' => [$kdMilikMapel->id, $kdLain->id],
    ])->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $mapel));

    $this->assertSoftDeleted('kompetensi_dasar', ['id' => $kdMilikMapel->id]);
    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kdLain->id]);
});

it('bulk delete KD melewati KD yang memiliki soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdBebas = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $kdDipakai = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    Soal::factory()->create(['kompetensi_dasar_id' => $kdDipakai->id]);

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar/bulk-delete", [
        'ids' => [$kdBebas->id, $kdDipakai->id],
    ])->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $mapel))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('kompetensi_dasar', ['id' => $kdBebas->id]);
    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kdDipakai->id]);
});

it('parameter all tidak lagi menghapus KD tanpa ids', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdPenalaran = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'level_kognitif' => 'penalaran']);
    $kdGabungan = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'level_kognitif' => 'pengetahuan_dan_pemahaman']);

    $this->actingAs($admin)->post("/admin/mapel/{$mapel->id}/kompetensi-dasar/bulk-delete", [
        'all' => true,
        'level_kognitif' => 'penalaran',
    ])->assertRedirect(route('admin.mapel.kompetensi-dasar.index', $mapel))
        ->assertSessionHas('error', 'Tidak ada kompetensi dasar yang dipilih.');

    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kdPenalaran->id]);
    $this->assertNotSoftDeleted('kompetensi_dasar', ['id' => $kdGabungan->id]);
});

it('index KD tidak lagi menampilkan tombol Hapus Semua', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->get("/admin/mapel/{$mapel->id}/kompetensi-dasar")
        ->assertOk()
        ->assertDontSee('Hapus Semua')
        ->assertSee('Hapus Terpilih');
});
