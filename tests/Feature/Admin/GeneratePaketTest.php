<?php

use App\Domain\Ai\AiFake;
use App\Domain\Ai\AiProvider;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\JsonOutputException;
use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\Soal;
use App\Models\User;

it('tamu yang membuka halaman generate dialihkan ke login', function () {
    $this->get('/admin/paket-soal/generate')->assertRedirect('/login');
    $this->get('/admin/paket-soal/kurasi')->assertRedirect('/login');
});

it('peserta tidak dapat membuka halaman generate (403)', function () {
    $user = User::factory()->peserta()->create();

    $this->actingAs($user)->get('/admin/paket-soal/generate')->assertForbidden();
});

it('admin dapat membuka halaman generate paket soal dari AI', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika Wajib']);
    KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->get('/admin/paket-soal/generate')
        ->assertOk()
        ->assertSee('Generate Paket Soal dari AI')
        ->assertSee('Matematika Wajib')
        ->assertSee('3.1')
        ->assertSee('Pilih semua KD');
});

it('validasi generate: mapel, kompetensi dasar, jumlah dan tingkat wajib', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/paket-soal/generate', [
        'mapel_id' => '',
        'kompetensi_dasar_ids' => [],
        'jumlah_soal' => '',
        'tingkat_kesulitan' => '',
        'part' => 1,
        'run' => 'run-1',
    ])->assertSessionHasErrors(['mapel_id', 'kompetensi_dasar_ids', 'jumlah_soal', 'tingkat_kesulitan']);
});

it('validasi generate: jumlah soal maksimal 30', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->actingAs($admin)->post('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 31,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ])->assertSessionHasErrors(['jumlah_soal']);
});

it('validasi generate: kompetensi dasar harus milik mapel yang dipilih', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kdMapelLain = KompetensiDasar::factory()->for(Mapel::factory())->create();

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kdMapelLain->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'mudah',
        'part' => 1,
        'run' => 'run-1',
    ])->assertStatus(422)
        ->assertJson(['ok' => false])
        ->assertSessionMissing('ai_parts');
});

it('part sukses mengakumulasi soal ke sesi ai_parts', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika']);
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ])->assertOk()
        ->assertJson([
            'ok' => true,
            'part' => 1,
            'part_total' => 1,
            'jumlah_akumulasi' => 3,
            'selesai' => true,
        ]);

    $parts = session('ai_parts');

    expect($parts['nama_paket'])->toBe('Paket AI Matematika')
        ->and($parts['daftar_soal'])->toHaveCount(3)
        ->and(array_column($parts['daftar_soal'], 'tipe_soal'))->toContain('pg', 'pg_kompleks', 'pg_kategori');
});

it('generate bertahap mengakumulasi soal antar part', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika']);
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 12,
        'tingkat_kesulitan' => 'campuran',
        'run' => 'run-1',
    ];

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 1])
        ->assertOk()
        ->assertJson(['part_total' => 3, 'jumlah_akumulasi' => 3, 'selesai' => false]);

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 2])
        ->assertOk()
        ->assertJson(['part_total' => 3, 'jumlah_akumulasi' => 6, 'selesai' => false]);

    expect(session('ai_parts.daftar_soal'))->toHaveCount(6);
});

it('generate berlanjut melewati perkiraan part_total saat yield per part kurang', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->app->instance(AiProvider::class, new class implements AiProvider
    {
        public function generate(string $prompt): string
        {
            return json_encode(AiFake::fixture());
        }
    });

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 12,
        'tingkat_kesulitan' => 'campuran',
        'run' => 'run-1',
    ];

    $ekspektasi = [
        1 => ['part_total' => 3, 'jumlah_akumulasi' => 3, 'selesai' => false],
        2 => ['part_total' => 3, 'jumlah_akumulasi' => 6, 'selesai' => false],
        3 => ['part_total' => 4, 'jumlah_akumulasi' => 9, 'selesai' => false],
        4 => ['part_total' => 4, 'jumlah_akumulasi' => 12, 'selesai' => true],
    ];

    foreach ($ekspektasi as $part => $json) {
        $respons = $this->actingAs($admin)
            ->postJson('/admin/paket-soal/generate', $payload + ['part' => $part])
            ->assertOk()
            ->assertJson(['part' => $part] + $json);

        expect($respons->json('part_total'))->toBeGreaterThanOrEqual($part);
    }

    expect(session('ai_parts.daftar_soal'))->toHaveCount(12);
});

it('part yang sama diproses ulang tidak menggandakan soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 12,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ];

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', $payload)->assertOk();
    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', $payload)
        ->assertOk()
        ->assertJson(['jumlah_part' => 0, 'jumlah_akumulasi' => 3]);

    expect(session('ai_parts.daftar_soal'))->toHaveCount(3);
});

it('run baru mereset akumulasi dari run sebelumnya', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 12,
        'tingkat_kesulitan' => 'campuran',
    ];

    session(['ai_draft' => ['mapel_id' => $mapel->id, 'daftar_soal' => []]]);

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 1, 'run' => 'run-a'])
        ->assertOk()
        ->assertJson(['jumlah_akumulasi' => 3]);

    expect(session('ai_draft'))->toBeNull();

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 1, 'run' => 'run-b'])
        ->assertOk()
        ->assertJson(['jumlah_part' => 3, 'jumlah_akumulasi' => 3]);

    expect(session('ai_parts.run'))->toBe('run-b')
        ->and(session('ai_parts.daftar_soal'))->toHaveCount(3);
});

it('nomor part di luar rentang ditolak', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'campuran',
        'run' => 'run-1',
    ];

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 2])
        ->assertStatus(422)
        ->assertJson(['ok' => false]);

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 99])
        ->assertStatus(422)
        ->assertJson(['ok' => false]);
});

it('kegagalan satu part mempertahankan bagian yang sudah terkumpul', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->app->instance(AiProvider::class, new class implements AiProvider
    {
        private int $panggilan = 0;

        public function generate(string $prompt): string
        {
            $this->panggilan++;

            if ($this->panggilan > 1) {
                throw new AiProviderException('Layanan AI sedang sibuk. Tunggu beberapa saat, lalu coba lagi.');
            }

            return json_encode(AiFake::fixture());
        }
    });

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 12,
        'tingkat_kesulitan' => 'campuran',
        'run' => 'run-1',
    ];

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 1])
        ->assertOk();

    $this->actingAs($admin)
        ->postJson('/admin/paket-soal/generate', $payload + ['part' => 2])
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => 'Layanan AI sedang sibuk. Tunggu beberapa saat, lalu coba lagi.']);

    expect(session('ai_parts.daftar_soal'))->toHaveCount(3)
        ->and(session('ai_parts.parts_done'))->toBe(1);
});

it('kurasi mematerialisasi draft dari ai_parts tanpa ai_draft', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Matematika']);
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ])->assertOk();

    expect(session('ai_draft'))->toBeNull();

    $this->actingAs($admin)->get('/admin/paket-soal/kurasi')
        ->assertOk()
        ->assertSee('Kurasi Paket Soal dari AI')
        ->assertSee('Hasil dari 2^3 x 2^5')
        ->assertSee('Kategorikan setiap pernyataan')
        ->assertSee('name="daftar_soal_json"', false)
        ->assertSee('name="daftar_soal[0][gambar_url]"', false);

    expect(session('ai_draft.daftar_soal'))->toHaveCount(3);
});

it('kurasi memetakan kode KD walau ditulis AI berbeda format', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();

    session(['ai_draft' => [
        'mapel_id' => $mapel->id,
        'nama_paket' => 'Paket AI Matematika',
        'deskripsi' => null,
        'daftar_soal' => [
            ['id_soal_sementara' => 'S001', 'tipe_soal' => 'pg', 'kompetensi_dasar_kode' => ' KD 3.1 ', 'pertanyaan' => 'Q1'],
            ['id_soal_sementara' => 'S002', 'tipe_soal' => 'pg', 'kompetensi_dasar_kode' => 'kd 3.2', 'pertanyaan' => 'Q2'],
            ['id_soal_sementara' => 'S003', 'tipe_soal' => 'pg', 'kompetensi_dasar_kode' => '3.1', 'pertanyaan' => 'Q3'],
        ],
    ]]);

    $html = $this->actingAs($admin)->get('/admin/paket-soal/kurasi')->assertOk()->getContent();

    expect(kdTerpilih(blokSelectKd($html, 0)))->toBe((string) $kd31->id)
        ->and(kdTerpilih(blokSelectKd($html, 1)))->toBe((string) $kd32->id)
        ->and(kdTerpilih(blokSelectKd($html, 2)))->toBe((string) $kd31->id);
});

it('kurasi menandai soal yang kode KD-nya tidak dikenal alih-alih membiarkannya diam-diam', function () {
    $admin = User::factory()->admin()->create();
    [$mapel] = setupKurasiMapel();

    session(['ai_draft' => [
        'mapel_id' => $mapel->id,
        'nama_paket' => 'Paket AI Matematika',
        'deskripsi' => null,
        'daftar_soal' => [
            ['id_soal_sementara' => 'S001', 'tipe_soal' => 'pg', 'kompetensi_dasar_kode' => '9.9', 'pertanyaan' => 'Q1'],
            ['id_soal_sementara' => 'S002', 'tipe_soal' => 'pg', 'kompetensi_dasar_kode' => null, 'pertanyaan' => 'Q2'],
        ],
    ]]);

    $html = $this->actingAs($admin)->get('/admin/paket-soal/kurasi')
        ->assertOk()
        ->assertSee('tidak ditemukan di Matematika', false)
        ->assertSee('AI tidak menyebut kode KD', false)
        ->assertSee('2 soal belum punya KD yang cocok', false)
        ->getContent();

    // Pilihan tetap pada placeholder supaya admin sadar harus memilih sendiri.
    expect(kdTerpilih(blokSelectKd($html, 0)))->toBeNull()
        ->and(kdTerpilih(blokSelectKd($html, 1)))->toBeNull();
});

it('kurasi tanpa draft dialihkan ke halaman generate', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/paket-soal/kurasi')
        ->assertRedirect(route('admin.paket-soal.generate'))
        ->assertSessionHas('error');
});

it('kegagalan layanan AI menampilkan pesan ramah tanpa menyimpan sesi', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->app->instance(AiProvider::class, new class implements AiProvider
    {
        public function generate(string $prompt): string
        {
            throw new AiProviderException('Gagal menghubungi layanan AI (kode status 500).');
        }
    });

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ])->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => 'Gagal menghubungi layanan AI (kode status 500).'])
        ->assertSessionMissing('ai_parts');
});

it('output AI yang bukan JSON menampilkan pesan ramah', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->app->instance(AiProvider::class, new class implements AiProvider
    {
        public function generate(string $prompt): string
        {
            return 'Maaf, saya tidak bisa menjawab.';
        }
    });

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ])->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => JsonOutputException::PESAN_RAMAH]);
});

it('simpan hasil kurasi membuat paket, soal, opsi, pernyataan dan detail', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertRedirect(route('admin.paket-soal.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('ai_draft')
        ->assertSessionMissing('ai_generate_input');

    $this->assertDatabaseHas('paket_soal', [
        'nama_paket' => 'Paket AI Matematika',
        'deskripsi' => 'Dihasilkan AI.',
        'mapel_id' => $mapel->id,
        'created_by' => $admin->id,
    ]);

    $paket = PaketSoal::where('nama_paket', 'Paket AI Matematika')->first();

    expect(DetailPaketSoal::where('paket_soal_id', $paket->id)->count())->toBe(3)
        ->and($paket->soal()->count())->toBe(3);

    $soalPg = Soal::where('pertanyaan', 'Hasil dari 2^3 x 2^5 adalah ...')->first();
    expect($soalPg->opsiJawaban()->count())->toBe(5)
        ->and($soalPg->opsiJawaban()->where('is_benar', true)->count())->toBe(1)
        ->and($soalPg->a_diskriminasi)->toBe(1.0);

    $soalKompleks = Soal::where('pertanyaan', 'Pernyataan yang benar tentang pangkat:')->first();
    expect($soalKompleks->opsiJawaban()->count())->toBe(5)
        ->and($soalKompleks->opsiJawaban()->where('is_benar', true)->count())->toBe(3);

    $soalKategori = Soal::where('pertanyaan', 'Kategorikan setiap pernyataan berikut:')->first();
    expect($soalKategori->tipe_soal)->toBe(Soal::TIPE_PG_KATEGORI)
        ->and($soalKategori->pernyataanKategori()->count())->toBe(3)
        ->and($soalKategori->daftar_kategori)->toBe(['Benar', 'Salah']);
});

it('simpan kurasi menolak PG dengan jumlah jawaban benar bukan satu', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    seedAiDraft($mapel);

    $payload['daftar_soal'][0]['opsi_jawaban'][1]['is_benar'] = 1;

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.0.opsi_jawaban');
});

it('simpan kurasi menolak PG Kompleks dengan minimal 2 benar tidak terpenuhi', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    seedAiDraft($mapel);

    $payload['daftar_soal'][1]['opsi_jawaban'][1]['is_benar'] = 0;
    $payload['daftar_soal'][1]['opsi_jawaban'][4]['is_benar'] = 0;

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.1.opsi_jawaban');
});

it('simpan kurasi menolak PG Kategori yang menyertakan opsi jawaban', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    seedAiDraft($mapel);

    $payload['daftar_soal'][2]['opsi_jawaban'] = [
        0 => ['urutan' => 1, 'teks_opsi' => 'ekstra', 'is_benar' => 1, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
    ];

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.2.opsi_jawaban');
});

it('simpan kurasi menolak opsi yang kehilangan teks_opsi', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    seedAiDraft($mapel);

    unset($payload['daftar_soal'][0]['opsi_jawaban'][2]['teks_opsi']);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.0.opsi_jawaban.2.teks_opsi');
});

it('simpan kurasi menolak pernyataan yang kehilangan kategori_benar', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    seedAiDraft($mapel);

    unset($payload['daftar_soal'][2]['pernyataan_kategori'][1]['kategori_benar']);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.2.pernyataan_kategori.1.kategori_benar');
});

it('simpan kurasi menolak ketika semua soal dihapus', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);

    seedAiDraft($mapel);

    foreach ($payload['daftar_soal'] as $index => $soal) {
        $payload['daftar_soal'][$index]['dihapus'] = 1;
    }

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal');

    $this->assertDatabaseCount('paket_soal', 0);
});

it('simpan kurasi tanpa sesi draft dialihkan ke generate', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', ['nama_paket' => 'Tanpa Draft'])
        ->assertRedirect(route('admin.paket-soal.generate'));
});

it('simpan kurasi menolak pertanyaan kosong (required)', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    seedAiDraft($mapel);

    $payload['daftar_soal'][0]['pertanyaan'] = '';

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.0.pertanyaan');
});

it('simpan kurasi menerima seluruh daftar soal lewat satu field JSON', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    $payload['daftar_soal_json'] = json_encode($payload['daftar_soal']);
    unset($payload['daftar_soal']);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertRedirect(route('admin.paket-soal.index'))
        ->assertSessionHas('success');

    $paket = PaketSoal::where('nama_paket', 'Paket AI Matematika')->first();

    expect($paket)->not->toBeNull()
        ->and($paket->soal()->count())->toBe(3)
        ->and($paket->soal()->first()->opsiJawaban()->count())->toBe(5);
});

it('daftar_soal_json yang bukan array valid ditolak tanpa menyimpan paket', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    $payload['daftar_soal_json'] = '{bukan-json';
    unset($payload['daftar_soal']);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal');

    $this->assertDatabaseCount('paket_soal', 0);
});

it('daftar_soal_json kosong ditolak', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    $payload['daftar_soal_json'] = '';
    unset($payload['daftar_soal']);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal');

    $this->assertDatabaseCount('paket_soal', 0);
});

it('simpan kurasi menolak ketika jumlah soal terkirim tidak sama dengan yang dirender', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    session(['ai_draft' => ['mapel_id' => $mapel->id, 'daftar_soal' => $payload['daftar_soal']]]);

    $payload['daftar_soal_json'] = json_encode(array_slice($payload['daftar_soal'], 0, 2));
    unset($payload['daftar_soal']);

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal');

    $this->assertDatabaseCount('paket_soal', 0);
});

it('opsi jawaban antara 6 sampai 8 diterima, 9 ditolak', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);

    for ($index = 5; $index < 8; $index++) {
        $payload['daftar_soal'][0]['opsi_jawaban'][$index] = [
            'urutan' => $index + 1,
            'teks_opsi' => 'Opsi ekstra '.$index,
            'is_benar' => 0,
            'a_diskriminasi' => '',
            'b_kesulitan' => '',
            'c_tebakan' => '',
        ];
    }

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertRedirect(route('admin.paket-soal.index'));

    $soalPg = Soal::where('pertanyaan', 'Hasil dari 2^3 x 2^5 adalah ...')->first();

    expect($soalPg->opsiJawaban()->count())->toBe(8);

    seedAiDraft($mapel);

    $payload['daftar_soal'][0]['opsi_jawaban'][8] = [
        'urutan' => 9,
        'teks_opsi' => 'Opsi kelebihan',
        'is_benar' => 0,
        'a_diskriminasi' => '',
        'b_kesulitan' => '',
        'c_tebakan' => '',
    ];

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertSessionHasErrors('daftar_soal.0.opsi_jawaban');
});

it('gambar_url dari halaman kurasi ikut tersimpan', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    $payload['daftar_soal'][0]['gambar_url'] = 'https://example.com/soal.png';

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertRedirect(route('admin.paket-soal.index'));

    $this->assertDatabaseHas('soal', [
        'pertanyaan' => 'Hasil dari 2^3 x 2^5 adalah ...',
        'gambar_url' => 'https://example.com/soal.png',
    ]);
});

it('id soal sementara unik setelah beberapa part digabung', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->app->instance(AiProvider::class, new class implements AiProvider
    {
        public function generate(string $prompt): string
        {
            return json_encode(AiFake::fixture());
        }
    });

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 12,
        'tingkat_kesulitan' => 'campuran',
        'run' => 'run-1',
    ];

    for ($part = 1; $part <= 4; $part++) {
        $this->actingAs($admin)
            ->postJson('/admin/paket-soal/generate', $payload + ['part' => $part])
            ->assertOk();
    }

    $ids = array_column(session('ai_parts.daftar_soal'), 'id_soal_sementara');

    expect($ids)->toHaveCount(12)
        ->and(array_unique($ids))->toHaveCount(12)
        ->and($ids[0])->toBe('S001')
        ->and($ids[11])->toBe('S012');
});

it('distribusi KD tersebar ke seluruh KD sepanjang part generate', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create(['nama' => 'Informatika']);

    $kds = collect(range(1, 10))->map(fn (int $n): KompetensiDasar => KompetensiDasar::factory()->create([
        'mapel_id' => $mapel->id,
        'kode_kompetensi' => '3.'.$n,
        'deskripsi' => 'KD nomor '.$n,
    ]));

    // AI tiruan yang menuruti target per KD di prompt, sehingga alur akumulasi
    // antar part sama seperti AI asli yang mengikuti instruksi.
    $perekam = new class implements AiProvider
    {
        /** @var array<int, string> */
        public array $prompt = [];

        public function generate(string $prompt): string
        {
            $this->prompt[] = $prompt;

            preg_match('/Buatkan (\d+) soal/u', $prompt, $diminta);
            preg_match_all('/KD ([0-9][0-9.]*) - .*?\(target: (\d+) soal\)/su', $prompt, $jadwal, PREG_SET_ORDER);

            $template = AiFake::fixture()['daftar_soal'];
            $daftarSoal = [];
            $nomor = 0;

            foreach ($jadwal as $baris) {
                for ($n = 0; $n < (int) $baris[2]; $n++) {
                    $soal = $template[$nomor % count($template)];
                    $soal['id_soal_sementara'] = 'S'.($nomor + 1);
                    $soal['pertanyaan'] = 'Pertanyaan nomor '.($nomor + 1).' untuk KD '.$baris[1].'?';
                    $soal['kompetensi_dasar'] = ['kode' => $baris[1], 'deskripsi' => 'KD nomor '.$baris[1]];
                    $daftarSoal[] = $soal;
                    $nomor++;
                }
            }

            $payload = AiFake::fixture();
            $payload['metadata']['jumlah_soal'] = count($daftarSoal);
            $payload['daftar_soal'] = $daftarSoal;

            return json_encode($payload);
        }
    };

    $this->app->instance(AiProvider::class, $perekam);

    $payload = [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => $kds->pluck('id')->all(),
        'jumlah_soal' => 30,
        'tingkat_kesulitan' => 'campuran',
        'run' => 'run-1',
    ];

    $respons = null;

    for ($part = 1; $part <= 20; $part++) {
        $respons = $this->actingAs($admin)
            ->postJson('/admin/paket-soal/generate', $payload + ['part' => $part])
            ->assertOk();

        if ($respons->json('selesai')) {
            break;
        }
    }

    expect($respons->json('selesai'))->toBeTrue()
        ->and($perekam->prompt)->toHaveCount(5);

    $kodeTerpanggil = [];

    foreach ($perekam->prompt as $prompt) {
        // Prompt tidak boleh menyuruh AI mengabaikan KD-nya sendiri, dan jumlah
        // target per KD harus persis sama dengan jumlah soal yang diminta.
        expect($prompt)->not->toContain('target: 0 soal');

        preg_match('/Buatkan (\d+) soal/u', $prompt, $diminta);
        preg_match_all('/\(target: (\d+) soal\)/u', $prompt, $target);

        expect(array_sum(array_map('intval', $target[1])))->toBe((int) $diminta[1]);

        preg_match_all('/KD ([0-9][0-9.]*) - /u', $prompt, $cocok);
        $kodeTerpanggil = array_merge($kodeTerpanggil, $cocok[1]);
    }

    expect(array_values(array_unique($kodeTerpanggil)))
        ->toEqualCanonicalizing($kds->pluck('kode_kompetensi')->values()->all());
});

it('soal yang dibuang otomatis diberi nomor part agar tidak ambigu', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id]);

    $this->app->instance(AiProvider::class, new class implements AiProvider
    {
        public function generate(string $prompt): string
        {
            $fixture = AiFake::fixture();
            $fixture['daftar_soal'][0]['pertanyaan'] = '';

            return json_encode($fixture);
        }
    });

    $this->actingAs($admin)->postJson('/admin/paket-soal/generate', [
        'mapel_id' => $mapel->id,
        'kompetensi_dasar_ids' => [$kd->id],
        'jumlah_soal' => 3,
        'tingkat_kesulitan' => 'campuran',
        'part' => 1,
        'run' => 'run-1',
    ])->assertOk();

    expect(session('ai_parts.soal_dibuang'))->toHaveCount(1)
        ->and(session('ai_parts.soal_dibuang.0.id'))->toBe('Part 1 · S001');
});

function seedAiDraft(Mapel $mapel): void
{
    session(['ai_draft' => ['mapel_id' => $mapel->id]]);
}

/**
 * Blok <select> Kompetensi Dasar milik satu kartu soal, untuk memeriksa opsi
 * mana yang terpilih.
 */
function blokSelectKd(string $html, int $index): string
{
    preg_match('/<select name="daftar_soal\['.$index.'\]\[kompetensi_dasar_id\]".*?<\/select>/su', $html, $cocok);

    return $cocok[0] ?? '';
}

/**
 * ID KD yang terpilih pada blok select, atau null kalau masih pada placeholder.
 */
function kdTerpilih(string $blok): ?string
{
    preg_match('/<option value="(\d+)"[^>]*selected[^>]*>/su', $blok, $cocok);

    return $cocok[1] ?? null;
}

function setupKurasiMapel(): array
{
    $mapel = Mapel::factory()->create(['nama' => 'Matematika']);
    $kd31 = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.1']);
    $kd32 = KompetensiDasar::factory()->create(['mapel_id' => $mapel->id, 'kode_kompetensi' => '3.2']);

    return [$mapel, $kd31, $kd32];
}

function kurasiPayloadValid(Mapel $mapel, KompetensiDasar $kd31, KompetensiDasar $kd32): array
{
    return [
        'nama_paket' => 'Paket AI Matematika',
        'deskripsi' => 'Dihasilkan AI.',
        'daftar_soal' => [
            [
                'id_soal_sementara' => 'S001',
                'tipe_soal' => 'pg',
                'kompetensi_dasar_id' => $kd31->id,
                'pertanyaan' => 'Hasil dari 2^3 x 2^5 adalah ...',
                'pembahasan' => 'a^m x a^n = a^(m+n).',
                'a_diskriminasi' => '1.0',
                'b_kesulitan' => '-0.3',
                'c_tebakan' => '0.2',
                'opsi_jawaban' => [
                    0 => ['urutan' => 1, 'teks_opsi' => '2^8', 'is_benar' => 1, 'a_diskriminasi' => '1.2', 'b_kesulitan' => '-0.5', 'c_tebakan' => '0.2'],
                    1 => ['urutan' => 2, 'teks_opsi' => '2^15', 'is_benar' => 0, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                    2 => ['urutan' => 3, 'teks_opsi' => '2^6', 'is_benar' => 0, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                    3 => ['urutan' => 4, 'teks_opsi' => '4^8', 'is_benar' => 0, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                    4 => ['urutan' => 5, 'teks_opsi' => '8^5', 'is_benar' => 0, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                ],
            ],
            [
                'id_soal_sementara' => 'S002',
                'tipe_soal' => 'pg_kompleks',
                'kompetensi_dasar_id' => $kd31->id,
                'pertanyaan' => 'Pernyataan yang benar tentang pangkat:',
                'pembahasan' => 'Pembahasan PG kompleks.',
                'a_diskriminasi' => '1.1',
                'b_kesulitan' => '0.1',
                'c_tebakan' => '0.15',
                'opsi_jawaban' => [
                    0 => ['urutan' => 1, 'teks_opsi' => 'a^0 = 1 untuk a != 0', 'is_benar' => 1, 'a_diskriminasi' => '1.5', 'b_kesulitan' => '-0.8', 'c_tebakan' => '0.1'],
                    1 => ['urutan' => 2, 'teks_opsi' => 'a^-n = 1/a^n', 'is_benar' => 1, 'a_diskriminasi' => '1.3', 'b_kesulitan' => '-0.3', 'c_tebakan' => '0.15'],
                    2 => ['urutan' => 3, 'teks_opsi' => '(a^m)^n = a^(m+n)', 'is_benar' => 0, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                    3 => ['urutan' => 4, 'teks_opsi' => 'a^m x a^n = a^(m*n)', 'is_benar' => 0, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                    4 => ['urutan' => 5, 'teks_opsi' => '(ab)^n = a^n b^n', 'is_benar' => 1, 'a_diskriminasi' => '', 'b_kesulitan' => '', 'c_tebakan' => ''],
                ],
            ],
            [
                'id_soal_sementara' => 'S003',
                'tipe_soal' => 'pg_kategori',
                'kompetensi_dasar_id' => $kd32->id,
                'pertanyaan' => 'Kategorikan setiap pernyataan berikut:',
                'pembahasan' => 'Pembahasan kategori.',
                'a_diskriminasi' => '1.2',
                'b_kesulitan' => '-0.05',
                'c_tebakan' => '0.22',
                'daftar_kategori' => ['Benar', 'Salah'],
                'pernyataan_kategori' => [
                    0 => ['urutan' => 1, 'teks_pernyataan' => 'HTML adalah bahasa markup.', 'kategori_benar' => 'Benar', 'a_diskriminasi' => '1.0', 'b_kesulitan' => '-0.4', 'c_tebakan' => '0.25'],
                    1 => ['urutan' => 2, 'teks_pernyataan' => 'JavaScript berjalan di server saja.', 'kategori_benar' => 'Salah', 'a_diskriminasi' => '1.6', 'b_kesulitan' => '0.3', 'c_tebakan' => '0.15'],
                    2 => ['urutan' => 3, 'teks_pernyataan' => 'CSS hanya mengatur tampilan halaman.', 'kategori_benar' => 'Benar', 'a_diskriminasi' => '1.3', 'b_kesulitan' => '-0.2', 'c_tebakan' => '0.2'],
                ],
            ],
        ],
    ];
}

it('menyaring konten WYSIWYG yang disimpan dari halaman kurasi (6.12)', function () {
    $admin = User::factory()->admin()->create();
    [$mapel, $kd31, $kd32] = setupKurasiMapel();
    seedAiDraft($mapel);

    $payload = kurasiPayloadValid($mapel, $kd31, $kd32);
    $payload['daftar_soal'][0]['pertanyaan'] = '<p>Soal <strong>aman</strong></p><script>alert(1)</script>';
    $payload['daftar_soal'][0]['pembahasan'] = '<p onclick="evil()">pembahasan</p>';
    $payload['daftar_soal'][0]['opsi_jawaban'][0]['teks_opsi'] = '<a href="javascript:alert(1)">2^8</a>';
    $payload['daftar_soal'][2]['pernyataan_kategori'][0]['teks_pernyataan'] = '<img src="x" onerror="alert(1)">HTML adalah bahasa markup.';

    $this->actingAs($admin)->post('/admin/paket-soal/simpan', $payload)
        ->assertRedirect(route('admin.paket-soal.index'))
        ->assertSessionHas('success');

    $soal = Soal::where('pertanyaan', '<p>Soal <strong>aman</strong></p>')->firstOrFail();

    expect($soal->pembahasan)->toBe('<p>pembahasan</p>')
        ->and($soal->opsiJawaban()->orderBy('urutan')->first()->teks_opsi)->toBe('<a>2^8</a>')
        ->and(Soal::where('pertanyaan', 'like', '%<script>%')->exists())->toBeFalse();
});
