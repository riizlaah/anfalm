<?php

use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\PaketSoal;
use App\Models\Soal;
use App\Models\User;

function payloadSoal(array $overrides = []): array
{
    return array_merge([
        'tipe_soal' => Soal::TIPE_PG,
        'pertanyaan' => 'Berapa hasil dari 2 + 2?',
        'pembahasan' => '2 + 2 = 4.',
        'a_diskriminasi' => 1.0,
        'b_kesulitan' => 0.0,
        'c_tebakan' => 0.25,
        'opsi_jawaban' => [
            ['teks_opsi' => '1', 'is_benar' => false, 'urutan' => 1],
            ['teks_opsi' => '2', 'is_benar' => false, 'urutan' => 2],
            ['teks_opsi' => '3', 'is_benar' => false, 'urutan' => 3],
            ['teks_opsi' => '4', 'is_benar' => true, 'urutan' => 4],
            ['teks_opsi' => '5', 'is_benar' => false, 'urutan' => 5],
        ],
    ], $overrides);
}

it('tamu yang membuka /admin/soal dialihkan ke login', function () {
    $this->get('/admin/soal')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin/soal (403)', function () {
    $this->actingAs(User::factory()->peserta()->create())
        ->get('/admin/soal')->assertForbidden();
});

it('admin dapat membuka halaman index soal', function () {
    $admin = User::factory()->admin()->create();
    Soal::factory()->create(['pertanyaan' => 'Soal terlihat di index']);

    $this->actingAs($admin)->get('/admin/soal')
        ->assertOk()
        ->assertSee('Manajemen Soal')
        ->assertSee('Soal terlihat di index');
});

it('admin dapat membuat soal PG', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/soal',
        payloadSoal(['kompetensi_dasar_id' => $kd->id]));

    $response->assertRedirect(route('admin.soal.index'));

    $this->assertDatabaseHas('soal', [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => 'pg',
        'pertanyaan' => 'Berapa hasil dari 2 + 2?',
    ]);

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();
    $this->assertDatabaseHas('opsi_jawaban', ['soal_id' => $soal->id, 'teks_opsi' => '4', 'is_benar' => true]);
    $this->assertDatabaseHas('opsi_jawaban', ['soal_id' => $soal->id, 'teks_opsi' => '1', 'is_benar' => false]);
});

it('soal PG dengan jumlah benar selain satu ditolak', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => [
            ['teks_opsi' => 'A', 'is_benar' => false, 'urutan' => 1],
            ['teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2],
            ['teks_opsi' => 'C', 'is_benar' => false, 'urutan' => 3],
            ['teks_opsi' => 'D', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'E', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertSessionHasErrors(['opsi_jawaban']);

    $this->actingAs($admin)->post('/admin/soal', payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => [
            ['teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1],
            ['teks_opsi' => 'B', 'is_benar' => true, 'urutan' => 2],
            ['teks_opsi' => 'C', 'is_benar' => true, 'urutan' => 3],
            ['teks_opsi' => 'D', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'E', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertSessionHasErrors(['opsi_jawaban']);
});

it('validasi soal: KD, tipe_soal dan pertanyaan wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/soal', [])
        ->assertSessionHasErrors(['kompetensi_dasar_id', 'tipe_soal', 'pertanyaan']);
});

it('soal PG Kompleks dengan hanya satu jawaban benar ditolak (edge 6.15)', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KOMPLEKS,
        'opsi_jawaban' => [
            ['teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1],
            ['teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2],
            ['teks_opsi' => 'C', 'is_benar' => false, 'urutan' => 3],
            ['teks_opsi' => 'D', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'E', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertSessionHasErrors(['opsi_jawaban']);
});

it('soal PG Kompleks dengan minimal dua jawaban benar dapat disimpan', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KOMPLEKS,
        'opsi_jawaban' => [
            ['teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1],
            ['teks_opsi' => 'B', 'is_benar' => true, 'urutan' => 2],
            ['teks_opsi' => 'C', 'is_benar' => false, 'urutan' => 3],
            ['teks_opsi' => 'D', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'E', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.soal.index'));

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();
    $this->assertEquals(5, $soal->opsiJawaban()->count());
    $this->assertEquals(2, $soal->opsiJawaban()->where('is_benar', true)->count());
});

it('soal PG Kategori wajib memiliki daftar_kategori dan pernyataan', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
    ]))->assertSessionHasErrors(['daftar_kategori', 'pernyataan_kategori']);
});

it('kategori_benar harus salah satu dari daftar_kategori', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => [
            ['teks_pernyataan' => 'p1', 'kategori_benar' => 'Mungkin', 'urutan' => 1],
        ],
    ])->assertSessionHasErrors(['pernyataan_kategori.0.kategori_benar']);
});

it('soal PG Kategori yang valid dapat disimpan beserta pernyataan', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => [
            ['teks_pernyataan' => 'p1', 'kategori_benar' => 'Benar', 'urutan' => 1],
            ['teks_pernyataan' => 'p2', 'kategori_benar' => 'Salah', 'urutan' => 2],
        ],
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.soal.index'));

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();
    $this->assertEquals(['Benar', 'Salah'], $soal->daftar_kategori);
    $this->assertEquals(2, $soal->pernyataanKategori()->count());
});

it('opsi_jawaban tidak diperbolehkan pada soal PG Kategori', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'opsi_jawaban' => [
            ['teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1],
        ],
        'pernyataan_kategori' => [
            ['teks_pernyataan' => 'p1', 'kategori_benar' => 'Benar', 'urutan' => 1],
        ],
    ])->assertSessionHasErrors(['opsi_jawaban']);
});

it('parameter IRT divalidasi rentangnya', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post('/admin/soal', payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'a_diskriminasi' => 3.0,
        'b_kesulitan' => 4.0,
        'c_tebakan' => 0.9,
    ]))->assertSessionHasErrors(['a_diskriminasi', 'b_kesulitan', 'c_tebakan']);
});

it('admin dapat mengedit soal PG beserta opsinya', function () {
    $admin = User::factory()->admin()->create();
    $kd = KompetensiDasar::factory()->create();
    $soal = Soal::factory()->create([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => 'Soal lama',
    ]);
    $soal->opsiJawaban()->create(['teks_opsi' => 'A', 'is_benar' => true]);
    $soal->opsiJawaban()->create(['teks_opsi' => 'B', 'is_benar' => false]);

    $this->actingAs($admin)->put("/admin/soal/{$soal->id}", payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => 'Soal baru',
        'opsi_jawaban' => [
            ['teks_opsi' => 'V', 'is_benar' => false, 'urutan' => 1],
            ['teks_opsi' => 'W', 'is_benar' => false, 'urutan' => 2],
            ['teks_opsi' => 'X', 'is_benar' => true, 'urutan' => 3],
            ['teks_opsi' => 'Y', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'Z', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertRedirect(route('admin.soal.index'));

    $soal->refresh();
    $this->assertEquals('Soal baru', $soal->pertanyaan);
    $opsi = $soal->opsiJawaban()->pluck('teks_opsi')->all();
    $this->assertEquals(['V', 'W', 'X', 'Y', 'Z'], $opsi);
});

it('soal yang belum dipakai paket dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $soal = Soal::factory()->create();

    $this->actingAs($admin)->delete("/admin/soal/{$soal->id}")
        ->assertRedirect(route('admin.soal.index'));

    $this->assertSoftDeleted('soal', ['id' => $soal->id]);
});

it('soal yang sudah masuk paket soal diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $soal = Soal::factory()->create();
    $paket = PaketSoal::factory()->create();
    DetailPaketSoal::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id]);

    $this->actingAs($admin)->delete("/admin/soal/{$soal->id}")
        ->assertRedirect(route('admin.soal.index'))
        ->assertSessionHas('error', 'Soal masih digunakan oleh paket soal, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('soal', ['id' => $soal->id]);
});
