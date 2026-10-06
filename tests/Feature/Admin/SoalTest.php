<?php

use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
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

function payloadPernyataan(int $jumlah = 3): array
{
    return array_map(fn (int $index): array => [
        'teks_pernyataan' => 'pernyataan '.($index + 1),
        'kategori_benar' => $index % 2 === 0 ? 'Benar' : 'Salah',
        'urutan' => $index + 1,
    ], range(0, $jumlah - 1));
}

it('tamu yang membuka daftar soal dialihkan ke login', function () {
    $mapel = Mapel::factory()->create();

    $this->get(route('admin.mapel.soal.index', $mapel))->assertRedirect('/login');
});

it('peserta tidak dapat membuka daftar soal (403)', function () {
    $mapel = Mapel::factory()->create();

    $this->actingAs(User::factory()->peserta()->create())
        ->get(route('admin.mapel.soal.index', $mapel))->assertForbidden();
});

it('admin dapat membuka halaman index soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    Soal::factory()->create(['kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id])]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk()
        ->assertSee('Manajemen Soal')
        ->assertSee($mapel->nama)
        ->assertSee('Menampilkan 1 soal.');
});

it('index soal hanya menampilkan soal milik mapel yang dipilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $mapelLain = Mapel::factory()->create();

    Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
        'pertanyaan' => 'Soal milik mapel terpilih',
    ]);
    Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapelLain->id]),
        'pertanyaan' => 'Soal milik mapel lain',
    ]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk()
        ->assertSee('Soal milik mapel terpilih')
        ->assertSee('Menampilkan 1 soal.')
        ->assertDontSee('Soal milik mapel lain');
});

it('index soal menampilkan jumlah soal sesuai filter', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd1 = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $kd2 = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    Soal::factory()->count(3)->create(['kompetensi_dasar_id' => $kd1->id]);
    Soal::factory()->count(2)->create(['kompetensi_dasar_id' => $kd2->id]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))->assertSee('Menampilkan 5 soal.');

    $this->actingAs($admin)
        ->get(route('admin.mapel.soal.index', $mapel).'?kompetensi_dasar_id='.$kd1->id)
        ->assertSee('Menampilkan 3 soal.');
});

it('index soal merender isi pertanyaan berformat HTML alih-alih meng-escapenya', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
        'pertanyaan' => '<p>Jawab dengan <strong>kunci</strong> ini.</p>',
    ]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk()
        ->assertSee('<strong>kunci</strong>', false);
});

it('index soal memuat pertanyaan utuh lalu memotongnya lewat CSS, bukan lewat potongan teks', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    // Lebih panjang dari batas 50 karakter yang dipakai pemotongan lama, sehingga
    // kalimat terakhir hanya bisa muncul bila seluruh isi ikut dirender.
    $pertanyaan = '<p>'.str_repeat('Konten pertanyaan yang panjang sekali. ', 5).'penutup-unik.</p>';

    Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
        'pertanyaan' => $pertanyaan,
    ]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk()
        ->assertSee('penutup-unik.', false)
        ->assertSee('line-clamp-3', false)
        ->assertSee('data-rumus', false);
});

it('index soal tidak melepas atribut berbahaya dari isi pertanyaan', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
        'pertanyaan' => '<p>Aman <img src="x" onerror="alert(1)"> ya.</p>',
    ]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk()
        ->assertSee('Aman', false)
        ->assertDontSee('onerror');
});

it('halaman index soal menampilkan jumlah soal per mapel dari menu mapel', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['kode' => 'MTKX']);
    Soal::factory()->count(2)->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
    ]);

    $this->actingAs($admin)->get(route('admin.mapel.index'))
        ->assertOk()
        ->assertSee('Kelola Soal (2)', false);
});

it('admin dapat membuat soal PG', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $response = $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel),
        payloadSoal(['kompetensi_dasar_id' => $kd->id]));

    $response->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $this->assertDatabaseHas('soal', [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => 'pg',
        'pertanyaan' => 'Berapa hasil dari 2 + 2?',
    ]);

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();
    $this->assertDatabaseHas('opsi_jawaban', ['soal_id' => $soal->id, 'teks_opsi' => '4', 'is_benar' => true]);
    $this->assertDatabaseHas('opsi_jawaban', ['soal_id' => $soal->id, 'teks_opsi' => '1', 'is_benar' => false]);
});

it('soal tidak bisa memakai KD dari mapel lain', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdMapelLain = KompetensiDasar::factory()->create();

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel),
        payloadSoal(['kompetensi_dasar_id' => $kdMapelLain->id]))
        ->assertSessionHasErrors('kompetensi_dasar_id');

    $this->assertDatabaseCount('soal', 0);
});

it('soal PG dengan jumlah benar selain satu ditolak', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $opsi = fn (array $benar): array => array_map(fn (int $index): array => [
        'teks_opsi' => 'Opsi '.($index + 1),
        'is_benar' => in_array($index, $benar, true),
        'urutan' => $index + 1,
    ], range(0, 4));

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => $opsi([]),
    ]))->assertSessionHasErrors(['opsi_jawaban']);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => $opsi([0, 1, 2]),
    ]))->assertSessionHasErrors(['opsi_jawaban']);
});

it('validasi soal: KD, tipe_soal dan pertanyaan wajib', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), [])
        ->assertSessionHasErrors(['kompetensi_dasar_id', 'tipe_soal', 'pertanyaan']);
});

it('soal PG Kompleks dengan hanya satu jawaban benar ditolak (edge 6.15)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
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
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
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
        ->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();
    $this->assertEquals(5, $soal->opsiJawaban()->count());
    $this->assertEquals(2, $soal->opsiJawaban()->where('is_benar', true)->count());
});

it('opsi jawaban manual boleh 6 sampai 8 dan ditolak di atas 8', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $opsi = fn (int $jumlah): array => array_map(
        fn (int $index): array => [
            'teks_opsi' => 'Opsi '.($index + 1),
            'is_benar' => $index === 0,
            'urutan' => $index + 1,
        ],
        range(0, $jumlah - 1)
    );

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => $opsi(8),
    ]))->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.mapel.soal.index', $mapel));

    expect(Soal::where('kompetensi_dasar_id', $kd->id)->firstOrFail()->opsiJawaban()->count())->toBe(8);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => $opsi(9),
    ]))->assertSessionHasErrors(['opsi_jawaban']);
});

it('soal PG Kategori wajib memiliki daftar_kategori dan pernyataan', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
    ]))->assertSessionHasErrors(['daftar_kategori', 'pernyataan_kategori']);
});

it('kategori_benar harus salah satu dari daftar_kategori', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => [
            ['teks_pernyataan' => 'p1', 'kategori_benar' => 'Mungkin', 'urutan' => 1],
            ['teks_pernyataan' => 'p2', 'kategori_benar' => 'Salah', 'urutan' => 2],
            ['teks_pernyataan' => 'p3', 'kategori_benar' => 'Salah', 'urutan' => 3],
        ],
    ])->assertSessionHasErrors(['pernyataan_kategori.0.kategori_benar']);
});

it('soal PG Kategori dengan dua pernyataan ditolak karena minimal tiga', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => payloadPernyataan(2),
    ])->assertSessionHasErrors(['pernyataan_kategori']);

    $this->assertDatabaseCount('soal', 0);
});

it('soal PG Kategori yang valid dapat disimpan beserta pernyataan', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => payloadPernyataan(3),
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();
    $this->assertEquals(['Benar', 'Salah'], $soal->daftar_kategori);
    $this->assertEquals(3, $soal->pernyataanKategori()->count());
});

it('opsi_jawaban yang bukan array ditolak tanpa error 500', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'opsi_jawaban' => 'bukan array',
    ]))->assertSessionHasErrors(['opsi_jawaban']);
});

it('pernyataan_kategori yang bukan array ditolak tanpa error 500', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => 'bukan array',
    ])->assertSessionHasErrors(['pernyataan_kategori']);
});

it('opsi_jawaban tidak diperbolehkan pada soal PG Kategori', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), [
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'pertanyaan' => 'Tentukan benar atau salah.',
        'daftar_kategori' => ['Benar', 'Salah'],
        'opsi_jawaban' => [
            ['teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1],
        ],
        'pernyataan_kategori' => payloadPernyataan(3),
    ])->assertSessionHasErrors(['opsi_jawaban']);
});

it('parameter IRT divalidasi rentangnya', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'a_diskriminasi' => 3.0,
        'b_kesulitan' => 4.0,
        'c_tebakan' => 0.9,
    ]))->assertSessionHasErrors(['a_diskriminasi', 'b_kesulitan', 'c_tebakan']);
});

it('form soal memeringatkan ketika parameter IRT masih default (edge 6.1)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->get(route('admin.mapel.soal.create', $mapel))
        ->assertOk()
        ->assertSee('Parameter IRT masih default, disarankan untuk dikurasi');
});

it('peringatan parameter IRT hilang setelah ketiganya dikurasi (edge 6.1)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->getKey()]);

    $soalDefault = Soal::factory()->create(['kompetensi_dasar_id' => $kd->getKey()]);
    $soalKurasi = Soal::factory()->create([
        'kompetensi_dasar_id' => $kd->getKey(),
        'a_diskriminasi' => 1.4,
        'b_kesulitan' => -0.8,
        'c_tebakan' => 0.2,
    ]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soalDefault]))
        ->assertOk()
        ->assertSee('Parameter IRT masih default, disarankan untuk dikurasi');

    $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soalKurasi]))
        ->assertOk()
        ->assertDontSee('Parameter IRT masih default');
});

it('form tambah soal sudah menampilkan jumlah baris minimum', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $html = $this->actingAs($admin)->get(route('admin.mapel.soal.create', $mapel))
        ->assertOk()
        ->getContent();

    foreach (range(0, 4) as $index) {
        expect($html)->toContain('name="opsi_jawaban['.$index.'][teks_opsi]"');
    }
    expect($html)->not->toContain('name="opsi_jawaban[5][teks_opsi]"');

    foreach (range(0, 2) as $index) {
        expect($html)->toContain('name="pernyataan_kategori['.$index.'][teks_pernyataan]"');
    }

    expect(substr_count($html, 'name="daftar_kategori[]" placeholder="Nama kategori (mis. Benar)"'))->toBe(2);
});

it('form tambah soal hanya menawarkan KD milik mapel terpilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdMapel = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => 'KD-PILIHAN']);
    $kdLain = KompetensiDasar::factory()->create(['kode_kompetensi' => 'KD-LAIN']);

    $html = $this->actingAs($admin)->get(route('admin.mapel.soal.create', $mapel))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<option value="KD-PILIHAN"></option>')
        ->not->toContain('<option value="KD-LAIN"></option>')
        ->and($html)->toContain('name="kompetensi_dasar_id" id="kompetensi_dasar_id" value=""');
});

it('halaman edit menampilkan kode KD pada input dan deskripsi penuh di bawahnya', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create([
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => 'KODE-1',
        'deskripsi' => 'Deskripsi KD yang sangat panjang sekali '.str_repeat('kata ', 40),
    ]);
    $soal = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);

    $html = $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soal]))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/id="kd-picker"[^>]*value="KODE-1"/s')
        ->and($html)->toContain('<span id="kd-detail-deskripsi">'.$kd->deskripsi.'</span>')
        ->and($html)->toContain('name="kompetensi_dasar_id" id="kompetensi_dasar_id" value="'.$kd->id.'"');
});

it('karakter HTML khusus tidak di-escape ganda saat mengedit soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal = Soal::factory()->create([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => 'Apa arti "investasi"?',
        'pembahasan' => ' Definisi <b>investasi</b> & risiko. ',
    ]);

    $html = $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soal]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Apa arti &quot;investasi&quot;?')
        ->and($html)->not->toContain('&amp;quot;')
        ->and($html)->not->toContain('&amp;lt;b&amp;gt;');
});

it('admin dapat mengedit soal PG beserta opsinya', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal = Soal::factory()->create([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => 'Soal lama',
    ]);
    $soal->opsiJawaban()->create(['teks_opsi' => 'A', 'is_benar' => true]);
    $soal->opsiJawaban()->create(['teks_opsi' => 'B', 'is_benar' => false]);

    $this->actingAs($admin)->put(route('admin.mapel.soal.update', [$mapel, $soal]), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => 'Soal baru',
        'opsi_jawaban' => [
            ['teks_opsi' => 'V', 'is_benar' => false, 'urutan' => 1],
            ['teks_opsi' => 'W', 'is_benar' => false, 'urutan' => 2],
            ['teks_opsi' => 'X', 'is_benar' => true, 'urutan' => 3],
            ['teks_opsi' => 'Y', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'Z', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $soal->refresh();
    $this->assertEquals('Soal baru', $soal->pertanyaan);
    $opsi = $soal->opsiJawaban()->pluck('teks_opsi')->all();
    $this->assertEquals(['V', 'W', 'X', 'Y', 'Z'], $opsi);
});

it('soal milik mapel lain tidak dapat dibuka atau diubah dari mapel ini', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $soalLain = Soal::factory()->create();

    $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soalLain]))->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.mapel.soal.destroy', [$mapel, $soalLain]))->assertNotFound();

    $this->assertNotSoftDeleted('soal', ['id' => $soalLain->id]);
});

it('halaman edit soal PG menampilkan opsi jawaban yang tersimpan', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id, 'pertanyaan' => 'Soal edit']);
    $soal->opsiJawaban()->create(['teks_opsi' => 'Hijau', 'is_benar' => true, 'urutan' => 1]);
    $soal->opsiJawaban()->create(['teks_opsi' => 'Merah', 'is_benar' => false, 'urutan' => 2]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soal]))
        ->assertOk()
        ->assertSee('Hijau')
        ->assertSee('Merah')
        ->assertSee('value="1"', false);
});

it('halaman edit soal PG Kategori menampilkan pernyataan yang tersimpan', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal = Soal::factory()->pgKategori()->create(['kompetensi_dasar_id' => $kd->id]);
    $soal->pernyataanKategori()->create(['teks_pernyataan' => 'Merah adalah warna', 'kategori_benar' => 'Benar', 'urutan' => 1]);
    $soal->pernyataanKategori()->create(['teks_pernyataan' => 'Langit berwarna hijau', 'kategori_benar' => 'Salah', 'urutan' => 2]);
    $soal->pernyataanKategori()->create(['teks_pernyataan' => 'Rumput hijau', 'kategori_benar' => 'Benar', 'urutan' => 3]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.edit', [$mapel, $soal]))
        ->assertOk()
        ->assertSee('Merah adalah warna')
        ->assertSee('Langit berwarna hijau')
        ->assertSee('Rumput hijau');
});

it('soal yang belum dipakai paket dapat dihapus (soft delete)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $soal = Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
    ]);

    $this->actingAs($admin)->delete(route('admin.mapel.soal.destroy', [$mapel, $soal]))
        ->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $this->assertSoftDeleted('soal', ['id' => $soal->id]);
});

it('soal yang sudah masuk paket soal diblokir dari hapus', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $soal = Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
    ]);
    $paket = PaketSoal::factory()->create();
    DetailPaketSoal::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id]);

    $this->actingAs($admin)->delete(route('admin.mapel.soal.destroy', [$mapel, $soal]))
        ->assertRedirect(route('admin.mapel.soal.index', $mapel))
        ->assertSessionHas('error', 'Soal masih digunakan oleh paket soal, tidak dapat dihapus.');

    $this->assertNotSoftDeleted('soal', ['id' => $soal->id]);
});

it('bulk delete soal menghapus soal yang dipilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal1 = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);
    $soal2 = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);
    $soal3 = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.bulk-delete', $mapel), [
        'ids' => [$soal1->id, $soal3->id],
    ])->assertRedirect(route('admin.mapel.soal.index', $mapel))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('soal', ['id' => $soal1->id]);
    $this->assertSoftDeleted('soal', ['id' => $soal3->id]);
    $this->assertNotSoftDeleted('soal', ['id' => $soal2->id]);
});

it('bulk delete soal melewati soal yang masih dipakai paket', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soalBebas = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);
    $soalDipakai = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);
    $paket = PaketSoal::factory()->create();
    DetailPaketSoal::create(['paket_soal_id' => $paket->id, 'soal_id' => $soalDipakai->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.bulk-delete', $mapel), [
        'ids' => [$soalBebas->id, $soalDipakai->id],
    ])->assertRedirect(route('admin.mapel.soal.index', $mapel))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('soal', ['id' => $soalBebas->id]);
    $this->assertNotSoftDeleted('soal', ['id' => $soalDipakai->id]);
});

it('parameter all tidak lagi menghapus soal tanpa ids', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);
    $soalLainFilter = Soal::factory()->create([
        'kompetensi_dasar_id' => KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]),
    ]);
    $soalMapelLain = Soal::factory()->create();

    $this->actingAs($admin)->post(route('admin.mapel.soal.bulk-delete', $mapel), [
        'all' => true,
        'kompetensi_dasar_id' => $kd->id,
    ])->assertRedirect(route('admin.mapel.soal.index', $mapel))
        ->assertSessionHas('error', 'Tidak ada soal yang dipilih.');

    $this->assertNotSoftDeleted('soal', ['id' => $soal->id]);
    $this->assertNotSoftDeleted('soal', ['id' => $soalLainFilter->id]);
    $this->assertNotSoftDeleted('soal', ['id' => $soalMapelLain->id]);
});

it('index soal tidak lagi menampilkan tombol Hapus Semua', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    Soal::factory()->create(['kompetensi_dasar_id' => $kd->id]);

    $this->actingAs($admin)->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk()
        ->assertDontSee('Hapus Semua')
        ->assertSee('Hapus Terpilih');
});

it('bulk delete soal tanpa pilihan menampilkan error', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();

    $this->actingAs($admin)->post(route('admin.mapel.soal.bulk-delete', $mapel), [])
        ->assertRedirect(route('admin.mapel.soal.index', $mapel))
        ->assertSessionHas('error');
});

it('menyaring konten WYSIWYG berbahaya sebelum disimpan (6.12)', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => '<p>Soal <strong>aman</strong></p><script>alert(1)</script>',
        'pembahasan' => '<p onclick="evil()">pembahasan</p>',
        'opsi_jawaban' => [
            ['teks_opsi' => '<script>x</script>Opsi A', 'is_benar' => false, 'urutan' => 1],
            ['teks_opsi' => '<a href="javascript:alert(1)">Opsi B</a>', 'is_benar' => false, 'urutan' => 2],
            ['teks_opsi' => 'Opsi C', 'is_benar' => true, 'urutan' => 3],
            ['teks_opsi' => 'Opsi D', 'is_benar' => false, 'urutan' => 4],
            ['teks_opsi' => 'Opsi E', 'is_benar' => false, 'urutan' => 5],
        ],
    ]))->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();

    expect($soal->pertanyaan)->toBe('<p>Soal <strong>aman</strong></p>')
        ->and($soal->pembahasan)->toBe('<p>pembahasan</p>')
        ->and($soal->opsiJawaban()->orderBy('urutan')->pluck('teks_opsi')->all())->toBe([
            'Opsi A',
            '<a>Opsi B</a>',
            'Opsi C',
            'Opsi D',
            'Opsi E',
        ]);
});

it('menyaring konten yang dikirim saat memperbarui soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);
    $soal = Soal::factory()->create(['kompetensi_dasar_id' => $kd->id, 'pertanyaan' => 'Soal lama']);

    $this->actingAs($admin)->put(route('admin.mapel.soal.update', [$mapel, $soal]), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => '<p>Baru</p><img src="x" onerror="alert(1)">',
    ]))->assertRedirect(route('admin.mapel.soal.index', $mapel));

    expect($soal->refresh()->pertanyaan)->toBe('<p>Baru</p><img src="x" />');
});

it('menyaring pernyataan PG Kategori sebelum disimpan', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'tipe_soal' => Soal::TIPE_PG_KATEGORI,
        'opsi_jawaban' => null,
        'daftar_kategori' => ['Benar', 'Salah'],
        'pernyataan_kategori' => array_map(fn (int $i): array => [
            'teks_pernyataan' => ($i === 0 ? '<script>x</script>' : '').'pernyataan '.($i + 1),
            'kategori_benar' => 'Benar',
            'urutan' => $i + 1,
        ], range(0, 2)),
    ]))->assertRedirect(route('admin.mapel.soal.index', $mapel));

    $soal = Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail();

    expect($soal->pernyataanKategori()->orderBy('urutan')->pluck('teks_pernyataan')->all())->toBe([
        'pernyataan 1',
        'pernyataan 2',
        'pernyataan 3',
    ]);
});

it('teks biasa tidak di-encode saat disimpan agar soal matematika tetap terbaca', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => "Jika i < n, hitung 2 + 2 = ?\nKemudian i = i + 1",
    ]))->assertRedirect(route('admin.mapel.soal.index', $mapel));

    expect(Soal::query()->where('kompetensi_dasar_id', $kd->id)->firstOrFail()->pertanyaan)
        ->toBe("Jika i < n, hitung 2 + 2 = ?\nKemudian i = i + 1");
});

it('menolak pertanyaan yang hanya berisi paragraf kosong dari editor', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post(route('admin.mapel.soal.store', $mapel), payloadSoal([
        'kompetensi_dasar_id' => $kd->id,
        'pertanyaan' => '<p></p>',
    ]))->assertSessionHasErrors('pertanyaan');

    $this->assertDatabaseCount('soal', 0);
});
