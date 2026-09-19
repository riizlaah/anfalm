<?php

use App\Domain\Ai\AiFake;
use App\Domain\Ai\SoalSkemaException;
use App\Domain\Ai\SoalSkemaValidator;

it('menormalkan paket dari output AI ke bentuk kurasi', function () {
    $hasil = (new SoalSkemaValidator)->validate(AiFake::fixture());

    expect($hasil)
        ->toHaveKey('nama_paket', 'Paket AI Matematika')
        ->toHaveKey('jumlah_soal', 3)
        ->toHaveKey('deskripsi', 'Dihasilkan AI.')
        ->toHaveKey('soal_dibuang', []);

    [$pg] = $hasil['daftar_soal'];

    expect($pg)
        ->toHaveKey('tipe_soal', 'pg')
        ->toHaveKey('kompetensi_dasar_kode', '3.1')
        ->toHaveKey('a_diskriminasi', 1.0)
        ->toHaveKey('b_kesulitan', -0.3)
        ->toHaveKey('c_tebakan', 0.2);
    expect($pg['opsi_jawaban'])->toHaveCount(5);
    expect($pg['opsi_jawaban'][0]['teks_opsi'])->toBe('2^8');
    expect($pg['opsi_jawaban'][0]['is_benar'])->toBeTrue();
    expect($pg['opsi_jawaban'][0]['a_diskriminasi'])->toBe(1.2);
});

it('mengisi opsi menjadi 5 saat AI mengembalikan kurang dari 5', function () {
    $payload = AiFake::fixture();
    $payload['daftar_soal'][0]['opsi_jawaban'] = array_slice($payload['daftar_soal'][0]['opsi_jawaban'], 0, 2);

    $hasil = (new SoalSkemaValidator)->validate($payload);

    expect($hasil['daftar_soal'][0]['opsi_jawaban'])->toHaveCount(5);
    expect($hasil['daftar_soal'][0]['opsi_jawaban'][2]['teks_opsi'])->toBe('');
    expect($hasil['daftar_soal'][0]['opsi_jawaban'][2]['is_benar'])->toBeFalse();
});

it('menormalkan pg_kategori dengan daftar kategori per soal', function () {
    $payload = AiFake::fixture();
    $payload['daftar_soal'][2]['kategori_pg_kategori'] = ['Setuju', 'Tidak Setuju'];
    $payload['daftar_soal'][2]['pernyataan_kategori'][0]['kategori_benar'] = 'Setuju';
    $payload['daftar_soal'][2]['pernyataan_kategori'][1]['kategori_benar'] = 'Tidak Setuju';

    $hasil = (new SoalSkemaValidator)->validate($payload);
    $soalKategori = $hasil['daftar_soal'][2];

    expect($soalKategori['daftar_kategori'])->toBe(['Setuju', 'Tidak Setuju']);
    expect($soalKategori['pernyataan_kategori'])->toHaveCount(2);
    expect($soalKategori['pernyataan_kategori'][0]['kategori_benar'])->toBe('Setuju');
    expect($soalKategori['pernyataan_kategori'][0]['teks_pernyataan'])->toBe('HTML adalah bahasa markup.');
});

it('membuang soal yang tidak memiliki minimal 2 pernyataan kategori valid', function () {
    $payload = AiFake::fixture();
    $payload['daftar_soal'][2]['pernyataan_kategori'][0]['kategori_benar'] = 'Tidak Valid';

    $hasil = (new SoalSkemaValidator)->validate($payload);

    expect($hasil['daftar_soal'])->toHaveCount(2);
    expect($hasil['soal_dibuang'])->toHaveCount(1);
    expect($hasil['soal_dibuang'][0]['alasan'])->toBe('pg_kategori membutuhkan minimal 2 pernyataan valid.');
});

it('membuang soal dengan tipe tidak dikenali dan tetap menormalkan sisanya', function () {
    $payload = AiFake::fixture();
    $payload['daftar_soal'][1]['tipe_soal'] = 'essai';

    $hasil = (new SoalSkemaValidator)->validate($payload);

    expect($hasil['daftar_soal'])->toHaveCount(2);
    expect($hasil['soal_dibuang'][0]['alasan'])->toBe('tipe_soal tidak dikenali.');
    expect($hasil['daftar_soal'][0]['id_soal_sementara'])->toBe('S001');
});

it('membuang soal tanpa pertanyaan', function () {
    $payload = AiFake::fixture();
    $payload['daftar_soal'][0]['pertanyaan'] = '';

    $hasil = (new SoalSkemaValidator)->validate($payload);

    expect($hasil['daftar_soal'])->toHaveCount(2);
    expect($hasil['soal_dibuang'][0]['alasan'])->toBe('pertanyaan kosong.');
});

it('menerima array polos berisi daftar soal tanpa metadata', function () {
    $payload = AiFake::fixture();

    $hasil = (new SoalSkemaValidator)->validate($payload['daftar_soal']);

    expect($hasil['daftar_soal'])->toHaveCount(3);
    expect($hasil['nama_paket'])->toContain('Paket');
});

it('menolak daftar soal kosong', function () {
    expect(fn () => (new SoalSkemaValidator)->validate([]))->toThrow(SoalSkemaException::class);
});

it('menolak melebihi batas maksimal 30 soal', function () {
    $payload = AiFake::fixture();
    $payload['daftar_soal'] = array_pad([], 31, $payload['daftar_soal'][0]);

    expect(fn () => (new SoalSkemaValidator)->validate($payload))
        ->toThrow(SoalSkemaException::class, 'maksimal 30');
});
