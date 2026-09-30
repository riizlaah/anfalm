<?php

use App\Domain\Percobaan\PercobaanService;
use App\Http\Controllers\TryoutController;
use App\Models\HasilTryout;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
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

it('membuat percobaan berjalan berisi daftar soal tiap mapel sesuai paket', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)
        ->post(route('tryout.mulai', $paket))
        ->assertRedirect(route('tryout.kerja', $paket));

    $percobaan = Percobaan::sole();

    expect($percobaan->user_id)->toBe($peserta->id)
        ->and($percobaan->jenis)->toBe(Percobaan::JENIS_TRYOUT)
        ->and($percobaan->status)->toBe(Percobaan::STATUS_BERJALAN)
        ->and($percobaan->urutan_mapel)->toBe(0)
        ->and($percobaan->batas_waktu_menit)->toBe($paket->batas_waktu_menit)
        ->and($percobaan->waktu_mulai)->not->toBeNull();

    $daftarSoal = $percobaan->daftar_soal;

    expect($daftarSoal)->toHaveCount(5);

    $totalSoal = 0;

    foreach (PaketTryout::SLOT as $index => $slot) {
        $mapelId = (int) $paket->getAttribute($slot['mapel']);
        $soalIds = PaketSoal::find($paket->getAttribute($slot['paket']))->soal()->get()->pluck('id')->all();

        expect($daftarSoal[$index]['mapel_id'])->toBe($mapelId)
            ->and($daftarSoal[$index]['soal_ids'])->toEqualCanonicalizing($soalIds);

        $totalSoal += count($soalIds);
    }

    expect($percobaan->jumlah_soal)->toBe($totalSoal);
});

it('mengacak urutan soal tiap percobaan tanpa mengurangi isinya', function () {
    $paket = PaketTryout::firstOrFail();
    $service = app(PercobaanService::class);

    $pertama = $service->susunDaftarSoal($paket);
    $kedua = $service->susunDaftarSoal($paket);

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

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));

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
        ->post(route('tryout.mulai', $paket))
        ->assertRedirect(route('tryout.index'))
        ->assertSessionHas('error', 'Anda sudah menyelesaikan tryout ini. Silakan hubungi admin jika ada masalah teknis.');

    expect(Percobaan::count())->toBe(0);
});

it('membuka halaman kerja pada mapel pertama', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));

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

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
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

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
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

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
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
        ->and($hasil->skor_konversi)->toBeLessThanOrEqual(700)
        ->and($hasil->total_soal)->toBe($hasil->jumlah_benar + $hasil->jumlah_salah)
        ->and($hasil->jumlah_benar)->toBe($aktif['soal']->count());
});

it('mengabaikan mapel yang tidak dijawab saat merata-ratakan theta', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
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
        ->and($hasil->skor_konversi)->toBe(700);
});

it('menampilkan halaman hasil setelah percobaan ditutup', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
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

it('menghitung batas akhir pengerjaan dari waktu mulai, bukan dari muat halaman', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $percobaan = Percobaan::sole();
    $waktuMulai = $percobaan->waktu_mulai->toIso8601String();

    $batasAkhir = $percobaan->waktu_mulai
        ->copy()
        ->addMinutes((int) $percobaan->batas_waktu_menit)
        ->toIso8601String();

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee($batasAkhir);

    // Memulai ulang tidak boleh mengulang hitung mundur dari nol.
    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));

    expect($percobaan->refresh()->waktu_mulai->toIso8601String())->toBe($waktuMulai);
});

it('menampilkan dialog konfirmasi sebelum pindah mapel', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertOk()
        ->assertSee('id="konfirmasi-pengerjaan"', false)
        ->assertSee('data-dialog-open="konfirmasi-pengerjaan"', false);
});

it('mengunci mapel yang sudah ditinggalkan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
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

it('menutup percobaan otomatis ketika batas waktu sudah lewat', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $percobaan = Percobaan::sole();
    $percobaan->update(['waktu_mulai' => now()->subMinutes((int) $percobaan->batas_waktu_menit + 5)]);

    $this->actingAs($peserta)
        ->get(route('tryout.kerja', $paket))
        ->assertRedirect(route('tryout.hasil', $paket));

    expect($percobaan->refresh()->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and(HasilTryout::count())->toBe(1);
});

it('tetap menyimpan jawaban yang terkirim setelah batas waktu lewat', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $percobaan->update(['waktu_mulai' => now()->subMinutes((int) $percobaan->batas_waktu_menit + 5)]);

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']))
        ->assertRedirect(route('tryout.hasil', $paket));

    expect(RiwayatPengerjaan::count())->toBe($aktif['soal']->count())
        ->and($percobaan->refresh()->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and($percobaan->durasi_detik)->toBe((int) $percobaan->batas_waktu_menit * 60);
});

it('menawarkan mulai ulang saat percobaan masih berjalan', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));

    $this->actingAs($peserta)
        ->get(route('tryout.index'))
        ->assertOk()
        ->assertSee(route('tryout.kerja', $paket))
        ->assertSee(route('tryout.ulang', $paket));
});

it('mengosongkan jawaban ketika peserta memilih mulai ulang', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)->post(route('tryout.jawab', $paket), payloadSemuaBenar($aktif['soal']));

    expect(RiwayatPengerjaan::count())->toBeGreaterThan(0);
    expect($percobaan->refresh()->urutan_mapel)->toBe(1);

    // Majukan waktu mulai supaya jelas terlihat bahwa hitung mundur diulang.
    $waktuMulaiLama = now()->subMinutes(30);
    $percobaan->update(['waktu_mulai' => $waktuMulaiLama]);

    $this->actingAs($peserta)
        ->post(route('tryout.ulang', $paket))
        ->assertRedirect(route('tryout.kerja', $paket));

    expect(RiwayatPengerjaan::count())->toBe(0)
        ->and(Percobaan::count())->toBe(1)
        ->and($percobaan->refresh()->urutan_mapel)->toBe(0);

    // Hitung mundur ikut diulang bersama jawabannya.
    expect($percobaan->waktu_mulai->greaterThan($waktuMulaiLama))->toBeTrue();
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

it('menutup percobaan ketika peserta memilih selesai di tengah mapel', function () {
    $paket = PaketTryout::firstOrFail();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->post(route('tryout.mulai', $paket));
    $percobaan = Percobaan::sole();
    $aktif = soalMapelAktif($percobaan);

    $this->actingAs($peserta)
        ->post(route('tryout.jawab', $paket), [...payloadSemuaBenar($aktif['soal']), 'aksi' => 'selesai'])
        ->assertRedirect(route('tryout.hasil', $paket));

    $percobaan->refresh();

    expect($percobaan->status)->toBe(Percobaan::STATUS_SELESAI)
        ->and($percobaan->urutan_mapel)->toBe(0)
        ->and(RiwayatPengerjaan::count())->toBe($aktif['soal']->count());
});
