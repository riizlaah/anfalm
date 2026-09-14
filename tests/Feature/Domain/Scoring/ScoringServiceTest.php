<?php

use App\Domain\Scoring\IrtService;
use App\Domain\Scoring\ScoringService;
use App\Models\OpsiJawaban;
use App\Models\PernyataanKategori;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->scoring = new ScoringService(new IrtService);
});

function buatSoalPG(string $tipe = Soal::TIPE_PG, array $attrs = []): Soal
{
    return Soal::factory()->create(array_merge(['tipe_soal' => $tipe], $attrs));
}

function tambahOpsi(Soal $soal, bool $benar, int $urutan, array $attrs = []): OpsiJawaban
{
    return ($benar ? OpsiJawaban::factory()->benar() : OpsiJawaban::factory())
        ->create(array_merge(['soal_id' => $soal->id, 'urutan' => $urutan], $attrs));
}

it('menghitung PG benar sebagai 1 item benar', function () {
    $soal = buatSoalPG(Soal::TIPE_PG);
    $benar = tambahOpsi($soal, true, 1);
    tambahOpsi($soal, false, 2);

    $hasil = $this->scoring->score($soal, ['opsi' => $benar->id]);

    expect($hasil['jumlah_benar'])->toBe(1)
        ->and($hasil['jumlah_salah'])->toBe(0)
        ->and($hasil['total_soal'])->toBe(1)
        ->and($hasil['items'][0]['resp'])->toBe(1);
});

it('menghitung PG salah sebagai 0 benar', function () {
    $soal = buatSoalPG();
    $benar = tambahOpsi($soal, true, 1);
    $salah = tambahOpsi($soal, false, 2);

    $hasil = $this->scoring->score($soal, ['opsi' => $salah->id]);

    expect($hasil['jumlah_benar'])->toBe(0)
        ->and($hasil['jumlah_salah'])->toBe(1)
        ->and($hasil['items'][0]['resp'])->toBe(0);
});

it('PG tanpa jawaban diabaikan seluruhnya (edge 6.2)', function () {
    $soal = buatSoalPG();
    tambahOpsi($soal, true, 1);
    tambahOpsi($soal, false, 2);

    $hasil = $this->scoring->score($soal, null);

    expect($hasil['jumlah_benar'])->toBe(0)
        ->and($hasil['jumlah_salah'])->toBe(0)
        ->and($hasil['total_soal'])->toBe(0)
        ->and($hasil['items'])->toBeEmpty();
});

it('PG dengan id opsi tidak dikenal dianggap salah', function () {
    $soal = buatSoalPG();
    tambahOpsi($soal, true, 1);
    tambahOpsi($soal, false, 2);

    $hasil = $this->scoring->score($soal, ['opsi' => 99999]);

    expect($hasil['items'][0]['resp'])->toBe(0)
        ->and($hasil['jumlah_benar'])->toBe(0);
});

it('PG menggunakan parameter IRT tingkat soal', function () {
    $soal = buatSoalPG(Soal::TIPE_PG, [
        'a_diskriminasi' => 1.5,
        'b_kesulitan' => 0.4,
        'c_tebakan' => 0.1,
    ]);
    $benar = tambahOpsi($soal, true, 1);

    $hasil = $this->scoring->score($soal, ['opsi' => $benar->id]);

    expect($hasil['items'][0]['a'])->toBe(1.5)
        ->and($hasil['items'][0]['b'])->toBe(0.4)
        ->and($hasil['items'][0]['c'])->toBe(0.1);
});

it('PG Kompleks dinilai per-opsi (kredit parsial)', function () {
    $soal = buatSoalPG(Soal::TIPE_PG_KOMPLEKS);
    // A benar, B salah, C benar, D salah
    tambahOpsi($soal, true, 1);   // A
    tambahOpsi($soal, false, 2);  // B
    tambahOpsi($soal, true, 3);   // C
    tambahOpsi($soal, false, 4);  // D
    $opsiA = $soal->opsiJawaban->get(0);
    $opsiB = $soal->opsiJawaban->get(1);

    // user pilih A (benar dipilih) dan B (salah dipilih); C & D tidak dipilih
    $hasil = $this->scoring->score($soal, ['opsi' => [$opsiA->id, $opsiB->id]]);

    expect($hasil['total_soal'])->toBe(4)
        ->and($hasil['items'])->toHaveCount(4)
        ->and($hasil['items'][0]['resp'])->toBe(1)      // A dipilih & benar
        ->and($hasil['items'][1]['resp'])->toBe(0)      // B dipilih & salah
        ->and($hasil['items'][2]['resp'])->toBe(0)      // C benar tapi tidak dipilih
        ->and($hasil['items'][3]['resp'])->toBe(1)      // D salah dan tidak dipilih
        ->and($hasil['jumlah_benar'])->toBe(2)
        ->and($hasil['jumlah_salah'])->toBe(2);
});

it('PG Kompleks tanpa jawaban diabaikan seluruhnya', function () {
    $soal = buatSoalPG(Soal::TIPE_PG_KOMPLEKS);
    tambahOpsi($soal, true, 1);
    tambahOpsi($soal, false, 2);

    $hasil = $this->scoring->score($soal, null);

    expect($hasil['total_soal'])->toBe(0)
        ->and($hasil['items'])->toBeEmpty();
});

it('PG Kompleks memakai parameter IRT per-opsi dan default saat null', function () {
    $soal = buatSoalPG(Soal::TIPE_PG_KOMPLEKS);
    tambahOpsi($soal, true, 1, [
        'a_diskriminasi' => 1.8,
        'b_kesulitan' => -0.7,
        'c_tebakan' => 0.15,
    ]);
    tambahOpsi($soal, false, 2); // semua param null

    $opsiA = $soal->opsiJawaban->get(0);
    $hasil = $this->scoring->score($soal, ['opsi' => [$opsiA->id]]);

    expect($hasil['items'][0]['a'])->toBe(1.8)
        ->and($hasil['items'][0]['b'])->toBe(-0.7)
        ->and($hasil['items'][0]['c'])->toBe(0.15)
        ->and($hasil['items'][1]['a'])->toBe(IrtService::DEFAULT_A)
        ->and($hasil['items'][1]['b'])->toBe(IrtService::DEFAULT_B)
        ->and($hasil['items'][1]['c'])->toBe(IrtService::DEFAULT_C);
});

it('PG Kategori dinilai per-pernyataan sesuai kategori benar', function () {
    $soal = buatSoalPG(Soal::TIPE_PG_KATEGORI, ['daftar_kategori' => ['Benar', 'Salah']]);
    $p1 = PernyataanKategori::factory()->create([   // benar: "Benar"
        'soal_id' => $soal->id,
        'kategori_benar' => 'Benar',
        'urutan' => 1,
    ]);
    $p2 = PernyataanKategori::factory()->create([   // benar: "Salah"
        'soal_id' => $soal->id,
        'kategori_benar' => 'Salah',
        'urutan' => 2,
    ]);

    $hasil = $this->scoring->score($soal, ['kategori' => [$p1->id => 'Benar', $p2->id => 'Benar']]);

    expect($hasil['total_soal'])->toBe(2)
        ->and($hasil['items'][0]['resp'])->toBe(1)
        ->and($hasil['items'][1]['resp'])->toBe(0)
        ->and($hasil['jumlah_benar'])->toBe(1)
        ->and($hasil['jumlah_salah'])->toBe(1);
});

it('PG Kategori dengan pernyataan tak dijawab diabaikan', function () {
    $soal = buatSoalPG(Soal::TIPE_PG_KATEGORI, ['daftar_kategori' => ['Benar', 'Salah']]);
    $p1 = PernyataanKategori::factory()->create([
        'soal_id' => $soal->id,
        'kategori_benar' => 'Benar',
        'urutan' => 1,
    ]);

    $hasil = $this->scoring->score($soal, ['kategori' => []]);

    expect($hasil['total_soal'])->toBe(0)
        ->and($hasil['items'])->toBeEmpty();
});

it('PG Kategori menolak nilai kategori di luar daftar yang didefinisikan', function () {
    $soal = buatSoalPG(Soal::TIPE_PG_KATEGORI, ['daftar_kategori' => ['Benar', 'Salah']]);
    $p1 = PernyataanKategori::factory()->create([
        'soal_id' => $soal->id,
        'kategori_benar' => 'Benar',
        'urutan' => 1,
    ]);

    $hasil = $this->scoring->score($soal, ['kategori' => [$p1->id => 'Mungkin']]);

    expect($hasil['total_soal'])->toBe(0)
        ->and($hasil['items'])->toHaveCount(1)
        ->and($hasil['items'][0]['resp'])->toBeNull();
});

it('melempar exception untuk tipe soal yang tidak dikenal', function () {
    $soal = new Soal(['tipe_soal' => 'tipe_tidak_ada']);

    expect(fn () => $this->scoring->score($soal, ['opsi' => []]))
        ->toThrow(InvalidArgumentException::class);
});
