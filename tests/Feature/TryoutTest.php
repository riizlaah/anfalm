<?php

use App\Domain\Percobaan\PercobaanService;
use App\Domain\Scoring\KompetensiLevel;
use App\Http\Controllers\TryoutController;
use App\Models\HasilTryout;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use App\Models\TrackingMapel;
use App\Models\User;

beforeEach(function () {
    $this->seed();
});

it('mengalihkan tamu ke halaman login', function () {
    $this->get(route('tryout.index'))->assertRedirect(route('login'));
});

it('menampilkan daftar paket tryout yang tersedia', function () {
    $paket = PaketTryout::firstOrFail();

    $this->actingAs(User::factory()->peserta()->create())
        ->get(route('tryout.index'))
        ->assertOk()
        ->assertSee($paket->nama_paket);
});

it('daftar tryout menampilkan tingkat sebagai SMA/SMK/Sederajat', function () {
    PaketTryout::firstOrFail();

    $this->actingAs(User::factory()->peserta()->create())
        ->get(route('tryout.index'))
        ->assertOk()
        ->assertSee('Tingkat SMA/SMK/Sederajat');
});

it('tetap menampilkan daftar tryout selama ada latihan yang belum selesai', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = Mapel::query()->whereNull('deleted_at')->orderBy('id')->firstOrFail();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'stopwatch',
    ]);

    // Latihan menyisakan percobaan berjalan tanpa paket tryout, sehingga
    // kumpulan `paket_tryout_id` berisi null dan `flip()` melempar error.
    $latihan = Percobaan::sole();

    expect($latihan->jenis)->toBe(Percobaan::JENIS_LATIHAN)
        ->and($latihan->paket_tryout_id)->toBeNull();

    $this->actingAs($peserta)
        ->get(route('tryout.index'))
        ->assertOk()
        ->assertSee(PaketTryout::firstOrFail()->nama_paket);
});

it('membuat percobaan berjalan berisi daftar soal tiap mapel sesuai paket', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $pilihan = duaMapelPilihan($paket);

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => $pilihan])
        ->assertRedirect(route('tryout.kerja', $paket));

    $percobaan = Percobaan::sole();

    expect($percobaan->user_id)->toBe($peserta->id)
        ->and($percobaan->jenis)->toBe(Percobaan::JENIS_TRYOUT)
        ->and($percobaan->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and($percobaan->urutan_mapel)->toBe(0)
        ->and($percobaan->batas_waktu_menit)->toBe(
            (int) $paket->daftarMapel()
                ->where('mapel_id', (int) $percobaan->daftar_soal[0]['mapel_id'])
                ->value('menit')
        )
        ->and($percobaan->waktu_mulai)->not->toBeNull();

    $daftarSoal = $percobaan->daftar_soal;
    $urutan = urutanMapelDicoba($paket, $pilihan);

    expect($daftarSoal)->toHaveCount(count($urutan));

    $totalSoal = 0;

    foreach ($urutan as $index => $mapelId) {
        $paketSoalId = (int) $paket->daftarMapel()
            ->where('mapel_id', $mapelId)
            ->value('paket_soal_id');
        $soalIds = PaketSoal::findOrFail($paketSoalId)->soal()->get()->pluck('id')->all();

        expect($daftarSoal[$index]['mapel_id'])->toBe($mapelId)
            ->and($daftarSoal[$index]['soal_ids'])->toEqualCanonicalizing($soalIds);

        $totalSoal += count($soalIds);
    }

    expect($percobaan->jumlah_soal)->toBe($totalSoal);
});

it('percobaan memakai batas waktu menit milik mapel yang sedang dikerjakan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $barisPertama = $paket->daftarMapelUrut()->first();
    $barisPertama->update(['menit' => 42]);

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)])
        ->assertRedirect(route('tryout.kerja', $paket));

    expect(Percobaan::sole()->batas_waktu_menit)->toBe(42);
});

it('mengacak urutan soal tiap percobaan tanpa mengurangi isinya', function () {
    $paket = PaketTryout::firstOrFail();
    $service = app(PercobaanService::class);

    $pilihan = duaMapelPilihan($paket);
    $pertama = $service->susunDaftarSoal($paket, $pilihan);
    $kedua = $service->susunDaftarSoal($paket, $pilihan);

    foreach ($pertama as $index => $grup) {
        expect($grup['mapel_id'])->toBe($kedua[$index]['mapel_id'])
            ->and($grup['soal_ids'])->toEqualCanonicalizing($kedua[$index]['soal_ids']);
    }

    $urutanIdentik = true;
    foreach ($pertama as $index => $grup) {
        if ($grup['soal_ids'] !== $kedua[$index]['soal_ids']) {
            $urutanIdentik = false;
        }
    }

    expect($urutanIdentik, 'urutan soal seharusnya diacak, bukan menyalin urutan paket')->toBeFalse();
});

it('melanjutkan percobaan yang masih berjalan alih-alih membuat yang baru', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);

    expect(Percobaan::count())->toBe(1);

    $this->actingAs($peserta)->get(route('tryout.kerja', $paket))->assertOk();
    $this->actingAs($peserta)->get(route('tryout.kerja', $paket))->assertOk();
});

it('menolak memulai ulang paket tryout yang sudah menghasilkan nilai', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    HasilTryout::factory()->create([
        'user_id' => $peserta->id,
        'paket_tryout_id' => $paket->id,
    ]);

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)])
        ->assertRedirect(route('tryout.index'))
        ->assertSessionHas('error', 'Anda sudah menyelesaikan tryout ini. Silakan hubungi admin jika ada masalah teknis.');

    expect(Percobaan::count())->toBe(0);
});

it('membuka halaman kerja pada mapel pertama', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);

    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee($aktif['soal']->first()->pertanyaan);
});

it('menyimpan jawaban satu mapel lalu mengunci mapel itu', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']))
        ->assertRedirect(route('tryout.kerja', $paket));

    $percobaan->refresh();

    expect($percobaan->urutan_mapel)->toBe(1)
        ->and(RiwayatPengerjaan::count())->toBe($aktif['soal']->count())
        ->and(
            RiwayatPengerjaan::pluck('soal_id')->all()
        )->toEqualCanonicalizing($aktif['soal']->pluck('id')->all());
});

it('mengabaikan jawaban untuk soal di luar mapel yang sedang dikerjakan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $soalBerikut = Soal::find($percobaan->daftar_soal[1]['soal_ids'][0]);

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), payloadSemuaBenar([$soalBerikut]));

    expect(RiwayatPengerjaan::count())->toBe(0)
        ->and($percobaan->refresh()->urutan_mapel)->toBe(1);
});

it('menghitung hasil pada mapel terakhir lalu menutup percobaan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    // Pindah ke mapel terakhir tanpa menjawab mapel di tengah sama sekali.
    $percobaan->update(['urutan_mapel' => 4]);
    $aktif = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']))
        ->assertRedirect(route('tryout.hasil', $paket));

    $percobaan->refresh();

    expect($percobaan->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and($percobaan->waktu_selesai)->not->toBeNull()
        ->and($percobaan->durasi_detik)->toBeGreaterThanOrEqual(0);

    $hasil = HasilTryout::where('user_id', $peserta->id)->where('paket_tryout_id', $paket->id)->sole();

    expect($hasil->theta_final)->toBeGreaterThan(0)
        ->and($hasil->skor_konversi)->toBeGreaterThanOrEqual(200)
        ->and($hasil->skor_konversi)->toBeLessThanOrEqual(800)
        ->and($hasil->total_soal)->toBe($hasil->jumlah_benar + $hasil->jumlah_salah)
        ->and($hasil->jumlah_benar)->toBe($aktif['soal']->count());
});

it('mengabaikan mapel yang tidak dijawab saat merata-ratakan theta', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    // Menjawab benar satu mapel pertama saja; empat mapel lain kosong.
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']));

    // Melewati tiga mapel berikutnya tanpa jawaban.
    $percobaan = Percobaan::sole();
    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    $hasil = HasilTryout::where('user_id', $peserta->id)->where('paket_tryout_id', $paket->id)->sole();

    // Mapel pertama dijawab seluruhnya benar (theta mapel = 3.0) dan mapel terakhir
    // juga seluruh benar, sehingga rata-rata dua mapel yang dikerjakan tetap 3.0 —
    // bukan ditarik turun oleh tiga mapel kosong.
    expect($hasil->theta_final)->toEqualWithDelta(3.0, 0.01)
        ->and($hasil->skor_konversi)->toBe(800);
});

it('menampilkan halaman hasil setelah percobaan ditutup', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    $hasil = HasilTryout::where('user_id', $peserta->id)->where('paket_tryout_id', $paket->id)->sole();

    $this->actingAs($peserta)
        ->get(route('tryout.hasil', $paket))
        ->assertOk()
        ->assertSee((string) $hasil->skor_konversi);
});

it('menampilkan level kompetensi per KD di halaman hasil tryout', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    // Mapel terakhir dijawab seluruh benar, jadi KD-nya naik ke Mahir;
    // empat mapel yang dilewati tetap belum teridentifikasi.
    $this->actingAs($peserta)
        ->get(route('tryout.hasil', $paket))
        ->assertOk()
        ->assertSee('Level kompetensi per KD')
        ->assertSee('Mahir')
        ->assertSee('Belum Teridentifikasi');
});

it('menyembunyikan kode KD dan statistik internal psikometrik dari halaman hasil', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    $kd = $terakhir['soal']->first()->kompetensiDasar;

    $halaman = (string) $this->actingAs($peserta)
        ->get(route('tryout.hasil', $paket))
        ->assertOk()
        ->getContent();

    $tampilan = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $halaman);

    // Galat baku dan theta mentah adalah statistik psikometrik yang tak pernah
    // dijelaskan di mana pun dan tak bisa ditindaklanjuti peserta; kode KD pun
    // demikian — deskripsinya yang menjelaskan isi kompetensi. Skor IRT tetap
    // tampil karena itulah kolomnya di leaderboard.
    expect($kd->deskripsi)->not->toBeEmpty()
        ->and($tampilan)->not->toContain('Galat baku')
        ->not->toContain('Theta')
        ->not->toContain($kd->kode_kompetensi)
        ->toContain($kd->deskripsi)
        ->toContain('Skor IRT total');
});

it('menampilkan ringkasan kompetensi per KD sebagai kartu, bukan tabel', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    // Layar 360px tidak seharusnya dipaksa menggulir tabel lima kolom: isinya
    // tetap utuh sebagai kartu. Matriks PG Kategori adalah satu-satunya tabel
    // yang dikecualikan aturan ini (laporan: "kecuali untuk PG Kategori"),
    // jadi dia dibuang dulu sebelum halaman dicek.
    $halaman = (string) $this->actingAs($peserta)
        ->get(route('tryout.hasil', $paket))
        ->assertOk()
        ->getContent();

    $tanpaMatriks = (string) preg_replace('/<table[^>]*matriks-kategori[^>]*>.*?<\/table>/s', '', $halaman);

    expect($tanpaMatriks)->not->toContain('<table')
        ->and($halaman)->toContain('Level kompetensi per KD')
        ->toContain('Mahir');
});

it('menandai kompetensi dasar yang belum pernah dijawab sebagai belum teridentifikasi', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $percobaan->update(['urutan_mapel' => 4]);

    // Seluruh mapel dilewati tanpa satu pun jawaban.
    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), ['aksi' => 'selesai'])
        ->assertRedirect(route('tryout.hasil', $paket));

    $this->actingAs($peserta)
        ->get(route('tryout.hasil', $paket))
        ->assertOk()
        ->assertDontSee('Mahir')
        ->assertSee('Belum Teridentifikasi');
});

it('menulis tracking mapel untuk tiap mapel pada paket tryout yang selesai', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    // Menjawab mapel terakhir saja; empat mapel sebelumnya dibiarkan kosong.
    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']))
        ->assertRedirect(route('tryout.hasil', $paket));

    $baris = TrackingMapel::query()->where('user_id', $peserta->getKey())->get()->keyBy('mapel_id');
    $mapelKosong = (int) $percobaan->daftar_soal[0]['mapel_id'];

    expect($baris)->toHaveCount(5)
        ->and($baris[$mapelKosong]->theta_estimasi)->toBeNull()
        ->and($baris[$mapelKosong]->level_kompetensi)->toBe(KompetensiLevel::BELUM_TERIDENTIFIKASI)
        ->and($baris[$mapelKosong]->total_tryout_diikuti)->toBe(0)
        ->and($baris[$mapelKosong]->rata_rata_skor_irt)->toBeNull();

    $mapelTerjawab = (int) $terakhir['urut'];

    expect($baris[$mapelTerjawab]->theta_estimasi)->not->toBeNull()
        ->and($baris[$mapelTerjawab]->level_kompetensi)->toBe(KompetensiLevel::MAHIR)
        ->and($baris[$mapelTerjawab]->total_tryout_diikuti)->toBe(1)
        ->and($baris[$mapelTerjawab]->rata_rata_skor_irt)->toEqualWithDelta(1.0, 0.01)
        ->and($baris[$mapelTerjawab]->last_updated)->not->toBeNull();
});

it('menghitung ulang theta mapel dari latihan tanpa menambah jumlah tryout', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    // Latihan pada mapel yang tadi dilewati sama sekali.
    $mapelLatihan = (int) $percobaan->refresh()->daftar_soal[0]['mapel_id'];

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapelLatihan,
        'jumlah_soal' => 5,
        'timer' => 'stopwatch',
    ]);

    $latihan = Percobaan::query()->where('jenis', Percobaan::JENIS_LATIHAN)->sole();
    $aktifLatihan = soalMapelAktif($latihan);

    $this->actingAs($peserta)->post(
        route('latihan.jawab', $latihan),
        [...payloadSemuaBenar($aktifLatihan['soal']), 'aksi' => 'selesai']
    );

    $baris = TrackingMapel::query()->where('user_id', $peserta->getKey())->get()->keyBy('mapel_id');

    // Theta terisi dari jawaban latihan, tetapi latihan bukan tryout jadi
    // penghitung tryout tetap 0 dan rata-rata skor tidak ikut berubah (3.9).
    expect($baris[$mapelLatihan]->theta_estimasi)->not->toBeNull()
        ->and($baris[$mapelLatihan]->level_kompetensi)->toBe(KompetensiLevel::MAHIR)
        ->and($baris[$mapelLatihan]->total_tryout_diikuti)->toBe(0)
        ->and($baris[$mapelLatihan]->rata_rata_skor_irt)->toBeNull()
        ->and($baris[(int) $terakhir['urut']]->total_tryout_diikuti)->toBe(1);
});

it('menghitung batas akhir pengerjaan dari waktu mulai mapel, bukan dari muat halaman', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $waktuMulai = $percobaan->waktu_mulai->toIso8601String();

    $batasAkhir = $percobaan->mulai_mapel
        ->copy()
        ->addMinutes((int) $percobaan->batas_waktu_menit)
        ->toIso8601String();

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee($batasAkhir);

    // Memulai ulang tidak boleh mengulang hitung mundur dari nol.
    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);

    expect($percobaan->refresh()->waktu_mulai->toIso8601String())->toBe($waktuMulai);
});

it('menampilkan dialog konfirmasi sebelum pindah mapel', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee('id="konfirmasi-pengerjaan"', false)
        ->assertSee('data-dialog-open="konfirmasi-pengerjaan"', false);
});

it('mengunci mapel yang sudah ditinggalkan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $pertanyaanMapelPertama = soalMapelAktif($percobaan)['soal']->first()->pertanyaan;

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket));

    $pertanyaanMapelKedua = soalMapelAktif($percobaan->refresh())['soal']->first()->pertanyaan;

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee($pertanyaanMapelKedua)
        ->assertDontSee($pertanyaanMapelPertama);
});

it('waktu habis mengunci mapel lalu lanjut ke mapel berikutnya, bukan menutup percobaan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $percobaan->update(['mulai_mapel' => now()->subMinutes((int) $percobaan->batas_waktu_menit + 5)]);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertRedirect(route('tryout.kerja', $paket));

    $percobaan->refresh();
    $mapelBerikut = (int) $percobaan->daftar_soal[(int) $percobaan->urutan_mapel]['mapel_id'];

    expect($percobaan->urutan_mapel)->toBe(1)
        ->and($percobaan->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and($percobaan->mulai_mapel->greaterThan(now()->subMinute()))->toBeTrue()
        ->and($percobaan->batas_waktu_menit)->toBe(
            (int) $paket->daftarMapel()->where('mapel_id', $mapelBerikut)->value('menit')
        )
        ->and(HasilTryout::count())->toBe(0);
});

it('waktu habis pada mapel terakhir menutup percobaan dan menghitung hasil', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $percobaan->update([
        'urutan_mapel' => count($percobaan->daftar_soal) - 1,
        'mulai_mapel' => now()->subMinutes((int) $percobaan->batas_waktu_menit + 5),
    ]);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertRedirect(route('tryout.hasil', $paket));

    expect($percobaan->refresh()->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and(HasilTryout::count())->toBe(1);
});

it('jawaban tetap tersimpan lalu mapel dikunci ketika batas waktunya lewat', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $percobaan->update(['mulai_mapel' => now()->subMinutes((int) $percobaan->batas_waktu_menit + 5)]);

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']))
        ->assertRedirect(route('tryout.kerja', $paket));

    expect(RiwayatPengerjaan::count())->toBe($aktif['soal']->count())
        ->and($percobaan->refresh()->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and($percobaan->urutan_mapel)->toBe(1);
});

it('durasi percobaan tidak melewati total menit seluruh mapel yang dikerjakan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $totalMenit = (int) $paket->daftarMapel()
        ->whereIn('mapel_id', collect($percobaan->daftar_soal)->pluck('mapel_id'))
        ->sum('menit');

    expect($totalMenit)->toBeGreaterThan((int) $percobaan->batas_waktu_menit);

    $percobaan->update([
        'urutan_mapel' => count($percobaan->daftar_soal) - 1,
        'waktu_mulai' => now()->subDays(3),
        'mulai_mapel' => now()->subDays(3),
    ]);

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), ['aksi' => 'selesai'])
        ->assertRedirect(route('tryout.hasil', $paket));

    expect($percobaan->refresh()->durasi_detik)->toBe($totalMenit * 60);
});

it('menawarkan mulai ulang saat percobaan masih berjalan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);

    $this->actingAs($peserta)
        ->get(route('tryout.index'))
        ->assertOk()
        ->assertSee(route('tryout.kerja', $paket))
        ->assertSee(route('tryout.ulang', $paket));
});

it('mengosongkan jawaban ketika peserta memilih mulai ulang', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']));

    expect(RiwayatPengerjaan::count())->toBeGreaterThan(0);
    expect($percobaan->refresh()->urutan_mapel)->toBe(1);

    // Majukan waktu mulai supaya jelas terlihat bahwa hitung mundur diulang.
    $waktuMulaiLama = now()->subMinutes(30);
    $percobaan->update(['waktu_mulai' => $waktuMulaiLama, 'mulai_mapel' => $waktuMulaiLama]);

    $this->actingAs($peserta)
        ->post(route('tryout.ulang', $paket))
        ->assertRedirect(route('tryout.kerja', $paket));

    expect(RiwayatPengerjaan::count())->toBe(0)
        ->and(Percobaan::count())->toBe(1)
        ->and($percobaan->refresh()->urutan_mapel)->toBe(0);

    // Hitung mundur ikut diulang bersama jawabannya.
    expect($percobaan->waktu_mulai->greaterThan($waktuMulaiLama))->toBeTrue()
        ->and($percobaan->mulai_mapel->greaterThan($waktuMulaiLama))->toBeTrue();
});

it('tetap menolak mulai ulang pada paket yang sudah menghasilkan nilai', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    HasilTryout::factory()->create([
        'user_id' => $peserta->id,
        'paket_tryout_id' => $paket->id,
    ]);

    $this->actingAs($peserta)
        ->post(route('tryout.ulang', $paket))
        ->assertRedirect(route('tryout.index'))
        ->assertSessionHas('error', TryoutController::PESAN_SUDAH_SELESAI);

    expect(Percobaan::count())->toBe(0);
});

it('aksi selesai pada mapel pertama tetap membuka mapel berikutnya', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    // Auto-submit hitung mundur mengirim `selesai` bahkan sebelum waktunya
    // lewat menurut server; ia tidak boleh menutup percobaan di tengah mapel
    // karena batas waktunya kini menempel per mapel.
    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'selesai'])
        ->assertRedirect(route('tryout.kerja', $paket));

    $percobaan->refresh();

    expect($percobaan->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and($percobaan->urutan_mapel)->toBe(1)
        ->and(RiwayatPengerjaan::count())->toBe($aktif['soal']->count());
});

/**
 * Peserta beserta hasil tryoutnya, sebagai satu baris kandidat leaderboard.
 */
function barisLeaderboard(PaketTryout $paket, string $nama, int $skor, int $durasi, int $menitLalu): User
{
    $peserta = User::factory()->peserta()->create(['nama_lengkap' => $nama]);

    HasilTryout::factory()->create([
        'user_id' => $peserta->getKey(),
        'paket_tryout_id' => $paket->getKey(),
        'skor_konversi' => $skor,
        'durasi_total' => $durasi,
        'selesai_pada' => now()->subMinutes($menitLalu),
    ]);

    return $peserta;
}

it('menampilkan leaderboard peserta yang sudah selesai saja diurutkan dari skor tertinggi', function () {
    $paket = PaketTryout::firstOrFail();

    barisLeaderboard($paket, 'Budi Rendah', 500, 2000, 1);
    $penonton = barisLeaderboard($paket, 'Siti Juara', 600, 3000, 2);

    // Peserta yang masih mengerjakan tidak boleh muncul (6.9).
    $sedang = User::factory()->peserta()->create(['nama_lengkap' => 'Andi Sedang']);
    $this->actingAs($sedang)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);

    $this->actingAs($penonton)
        ->get(route('tryout.leaderboard', $paket))
        ->assertOk()
        ->assertSeeInOrder(['Siti Juara', 'Budi Rendah'])
        ->assertDontSee('Andi Sedang');
});

it('menempatkan durasi lebih cepat di atas di leaderboard saat skor sama', function () {
    $paket = PaketTryout::firstOrFail();

    barisLeaderboard($paket, 'Rina Lambat', 550, 1800, 1);
    $penonton = barisLeaderboard($paket, 'Rina Cepat', 550, 900, 3);

    $this->actingAs($penonton)
        ->get(route('tryout.leaderboard', $paket))
        ->assertOk()
        ->assertSeeInOrder(['Rina Cepat', 'Rina Lambat']);
});

it('menempatkan yang selesai lebih dulu di atas di leaderboard saat skor dan durasi sama', function () {
    $paket = PaketTryout::firstOrFail();

    barisLeaderboard($paket, 'Tono Akhir', 550, 1200, 1);
    $penonton = barisLeaderboard($paket, 'Tono Awal', 550, 1200, 30);

    $this->actingAs($penonton)
        ->get(route('tryout.leaderboard', $paket))
        ->assertOk()
        ->assertSeeInOrder(['Tono Awal', 'Tono Akhir']);
});

it('menandai posisi peserta sendiri di leaderboard', function () {
    $paket = PaketTryout::firstOrFail();

    barisLeaderboard($paket, 'Eka Pertama', 650, 1000, 5);
    $penonton = barisLeaderboard($paket, 'Dewi Sendiri', 550, 2000, 4);
    barisLeaderboard($paket, 'Fajar Ketiga', 450, 3000, 3);

    $this->actingAs($penonton)
        ->get(route('tryout.leaderboard', $paket))
        ->assertOk()
        ->assertSee('Peringkat Anda: 2')
        ->assertSee('data-posisi-sendiri', false);
});

it('menampilkan leaderboard sebagai daftar kartu, bukan tabel', function () {
    $paket = PaketTryout::firstOrFail();

    barisLeaderboard($paket, 'Budi Rendah', 500, 2000, 1);
    $penonton = barisLeaderboard($paket, 'Siti Juara', 600, 3000, 2);

    $this->actingAs($penonton)
        ->get(route('tryout.leaderboard', $paket))
        ->assertOk()
        ->assertDontSee('<table', false)
        ->assertSeeInOrder(['Peringkat 1', 'Siti Juara', 'Skor IRT 600']);
});

it('menampilkan leaderboard kosong tanpa peserta yang sudah selesai', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)
        ->get(route('tryout.leaderboard', $paket))
        ->assertOk()
        ->assertSee('Belum ada peserta yang menyelesaikan tryout ini');
});

it('menampilkan pembahasan per soal di halaman hasil tryout (3.7)', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();

    $percobaan->update(['urutan_mapel' => 4]);
    $terakhir = soalMapelAktif($percobaan->refresh());
    $soal = $terakhir['soal']->first();

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($terakhir['soal']));

    $this->actingAs($peserta)
        ->get(route('tryout.hasil', $paket))
        ->assertOk()
        ->assertSee('Pembahasan')
        ->assertSee($soal->pembahasan)
        ->assertSee($soal->pertanyaan);
});

it('me-render konten HTML soal secara mentah sekaligus membuang skripnya (6.13)', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $soal = soalMapelAktif($percobaan)['soal']->first();

    $soal->update([
        'pertanyaan' => '<p>Soal <strong>penting</strong></p><script>alert(1)</script>',
        'pembahasan' => '<p onclick="evil()">Lihat pembahasan</p>',
    ]);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee('<strong>penting</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('alert(1)');
});

it('me-escape teks biasa saat ditampilkan agar karakter kurang dari tidak jadi tag', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket), ['pilihan' => duaMapelPilihan($paket)]);
    $percobaan = Percobaan::sole();
    $soal = soalMapelAktif($percobaan)['soal']->first();

    $soal->update(['pertanyaan' => 'Karena i < n, jawabannya 3']);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee('Karena i &lt; n, jawabannya 3', false)
        ->assertDontSee('Karena i < n, jawabannya 3', false);
});

/**
 * Urutan mapel yang seharusnya dikerjakan peserta: seluruh mapel wajib
 * menurut kode mapel, lalu sepasang pilihan yang dipilih peserta itu.
 *
 * @param  array<int, int>  $pilihan
 * @return array<int, int>
 */
function urutanMapelDicoba(PaketTryout $paket, array $pilihan): array
{
    $wajib = $paket->daftarMapel()
        ->with('mapel')
        ->get()
        ->filter(fn ($baris) => $baris->mapel->jenis === Mapel::JENIS_WAJIB)
        ->sortBy(fn ($baris) => $baris->mapel->kode)
        ->pluck('mapel_id');

    return $wajib->concat(collect($pilihan)->sort()->values())->all();
}

/** Seluruh mapel pilihan pada paket tryout, terurut menurut kode mapel. */
function semuaMapelPilihan(PaketTryout $paket): array
{
    return $paket->daftarMapel()
        ->with('mapel')
        ->get()
        ->filter(fn ($baris) => $baris->mapel->jenis !== Mapel::JENIS_WAJIB)
        ->sortBy(fn ($baris) => $baris->mapel->kode)
        ->pluck('mapel_id')
        ->values()
        ->all();
}

it('halaman pilihan menampilkan seluruh mapel pilihan milik paket tryout', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)
        ->get(route('tryout.pilih', $paket))
        ->assertOk()
        ->assertSee('Pilih 2 Mapel Pilihan')
        ->getContent();

    $pilihan = semuaMapelPilihan($paket);

    // Seeder mengisi seluruh mapel pilihan, bukan hanya dua milik admin.
    expect($pilihan)->toHaveCount(6);

    foreach ($pilihan as $mapelId) {
        expect($html)->toContain('name="pilihan[]" value="'.$mapelId.'"');
    }

    $wajib = $paket->daftarMapel()
        ->with('mapel')
        ->get()
        ->filter(fn ($baris) => $baris->mapel->jenis === Mapel::JENIS_WAJIB);

    foreach ($wajib as $baris) {
        expect($html)->toContain($baris->mapel->nama)
            ->not->toContain('name="pilihan[]" value="'.$baris->mapel_id.'"');
    }
});

it('peserta memilih dua mapel pilihan sendiri saat memulai tryout', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $semua = semuaMapelPilihan($paket);
    $dua = array_slice($semua, -2);

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => $dua])
        ->assertRedirect(route('tryout.kerja', $paket));

    expect(array_column(Percobaan::sole()->daftar_soal, 'mapel_id'))
        ->toBe(urutanMapelDicoba($paket, $dua));
});

it('menolak memulai tryout tanpa memilih tepat dua mapel pilihan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();
    $semua = semuaMapelPilihan($paket);

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => [$semua[0]]])
        ->assertSessionHasErrors('pilihan');

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => []])
        ->assertSessionHasErrors('pilihan');

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket))
        ->assertSessionHasErrors('pilihan');

    expect(Percobaan::count())->toBe(0);
});

it('menolak mapel wajib dan mapel di luar paket sebagai pilihan peserta', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();
    $semua = semuaMapelPilihan($paket);

    $wajib = $paket->daftarMapel()
        ->with('mapel')
        ->get()
        ->first(fn ($baris) => $baris->mapel->jenis === Mapel::JENIS_WAJIB)
        ->mapel_id;

    $luar = Mapel::factory()->create(['jenis' => Mapel::JENIS_PILIHAN_UMUM])->getKey();

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => [$wajib, $semua[0]]])
        ->assertSessionHasErrors('pilihan.0');

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket), ['pilihan' => [$semua[0], $luar]])
        ->assertSessionHasErrors('pilihan.1');

    expect(Percobaan::count())->toBe(0);
});
