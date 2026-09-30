<?php

use App\Domain\Percobaan\PercobaanService;
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

/**
 * Daftar soal tiap mapel pada posisi yang sedang dikerjakan.
 *
 * @return array{soal: Collection<int, Soal>, urut: int}
 */
function soalMapelAktif(Percobaan $percobaan): array
{
    $grup = $percobaan->daftar_soal[$percobaan->urutan_mapel];

    return [
        'soal' => Soal::with(['opsiJawaban', 'pernyataanKategori'])
            ->whereIn('id', $grup['soal_ids'])
            ->get()
            ->sortBy(fn (Soal $soal) => array_search($soal->id, $grup['soal_ids']))
            ->values(),
        'urut' => $grup['mapel_id'],
    ];
}

/**
 * Jawaban paling benar untuk satu soal, dalam bentuk yang dipakai form.
 *
 * @return array<int, mixed>
 */
function jawabanBenarSoal(Soal $soal): mixed
{
    return match ($soal->tipe_soal) {
        Soal::TIPE_PG => $soal->opsiJawaban->firstWhere('is_benar', true)->id,
        Soal::TIPE_PG_KOMPLEKS => $soal->opsiJawaban->filter->is_benar->pluck('id')->all(),
        Soal::TIPE_PG_KATEGORI => $soal->pernyataanKategori
            ->mapWithKeys(fn ($p) => [$p->id => $p->kategori_benar])
            ->all(),
    };
}

/**
 * Payload `jawaban[...]` untuk sekumpulan soal, memilih semua jawaban benar.
 *
 * @param  Collection<int, Soal>|array<int, Soal>  $soals
 * @return array<string, mixed>
 */
function payloadSemuaBenar($soals): array
{
    $opsi = [];
    $kategori = [];

    foreach ($soals as $soal) {
        if ($soal->tipe_soal === Soal::TIPE_PG_KATEGORI) {
            $kategori[$soal->id] = jawabanBenarSoal($soal);

            continue;
        }

        $opsi[$soal->id] = jawabanBenarSoal($soal);
    }

    return ['jawaban' => ['opsi' => $opsi, 'kategori' => $kategori]];
}

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
