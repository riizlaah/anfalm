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

it('index mapel menampilkan aksi kelola KD beserta jumlah KD', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->count(2)->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->get('/admin/mapel')
        ->assertOk()
        ->assertSee(route('admin.mapel.kompetensi-dasar.index', $mapel), false)
        ->assertSee('Kelola KD (2)');
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

it('gagal validasi membuka dialog tambah mapel dengan isian yang sama dan tanpa old input', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/mapel', [
        'kode' => '',
        'nama' => 'Kandidat Nama',
        'tingkat' => 'SMA',
        'jenis' => Mapel::JENIS_WAJIB,
    ])
        ->assertSessionHasErrors(['kode'])
        ->assertSessionHas('open_dialog', 'create-mapel')
        ->assertSessionHas('form_input', fn (array $input) => $input['nama'] === 'Kandidat Nama')
        ->assertSessionMissing('_old_input');
});

it('gagal validasi pada edit membuka dialog edit baris itu sendiri', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->put("/admin/mapel/{$mapel->id}", [
        'kode' => $mapel->kode,
        'nama' => '',
        'tingkat' => $mapel->tingkat,
        'jenis' => $mapel->jenis,
    ])
        ->assertSessionHasErrors(['nama'])
        ->assertSessionHas('open_dialog', "edit-mapel-{$mapel->id}")
        ->assertSessionMissing('_old_input');
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

it('kode mapel yang sama dengan mapel terhapus dapat dibuat kembali', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika Lama']);
    $mapel->delete();

    $this->actingAs($admin)->post('/admin/mapel', [
        'kode' => 'MTK',
        'nama' => 'Matematika Baru',
        'tingkat' => 'SMA',
        'jenis' => Mapel::JENIS_WAJIB,
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.mapel.index'));

    $this->assertDatabaseHas('mapel', [
        'kode' => 'MTK',
        'nama' => 'Matematika Baru',
        'deleted_at' => null,
    ]);
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

it('bulk delete mapel menghapus mapel yang dipilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel1 = Mapel::factory()->create();
    $mapel2 = Mapel::factory()->create();

    $this->actingAs($admin)->post('/admin/mapel/bulk-delete', [
        'ids' => [$mapel1->id],
    ])->assertRedirect(route('admin.mapel.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('mapel', ['id' => $mapel1->id]);
    $this->assertNotSoftDeleted('mapel', ['id' => $mapel2->id]);
});

it('bulk delete mapel melewati mapel yang memiliki KD atau paket soal', function () {
    $admin = User::factory()->admin()->create();
    $mapelBebas = Mapel::factory()->create();
    $mapelDipakai = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapelDipakai->id]);

    $this->actingAs($admin)->post('/admin/mapel/bulk-delete', [
        'ids' => [$mapelBebas->id, $mapelDipakai->id],
    ])->assertRedirect(route('admin.mapel.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('mapel', ['id' => $mapelBebas->id]);
    $this->assertNotSoftDeleted('mapel', ['id' => $mapelDipakai->id]);
});

it('parameter all tidak lagi menghapus apa pun tanpa ids', function () {
    $admin = User::factory()->admin()->create();
    $mapelSMA = Mapel::factory()->create(['tingkat' => Mapel::TINGKAT_SMA]);
    $mapelSMP = Mapel::factory()->create(['tingkat' => Mapel::TINGKAT_SMP]);

    // "Hapus Semua" dihapus dari halaman (butir laporan): tombol itu sudah lama
    // tidak bekerja — form mengirim `all` sebagai string "1" sementara controller
    // membandingkannya dengan boolean, sehingga permintaannya selalu ditolak
    // diam-diam. Pilih semua + hapus terpilih menutup kebutuhan yang sama.
    $this->actingAs($admin)->post('/admin/mapel/bulk-delete', [
        'all' => true,
        'tingkat' => Mapel::TINGKAT_SMA,
    ])->assertRedirect(route('admin.mapel.index'))
        ->assertSessionHas('error', 'Tidak ada mapel yang dipilih.');

    $this->assertNotSoftDeleted('mapel', ['id' => $mapelSMA->id]);
    $this->assertNotSoftDeleted('mapel', ['id' => $mapelSMP->id]);
});

it('index mapel tidak lagi menampilkan tombol Hapus Semua', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/mapel')
        ->assertOk()
        ->assertDontSee('Hapus Semua')
        ->assertSee('Hapus Terpilih');
});
