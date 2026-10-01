<?php

use App\Domain\Scoring\KompetensiLevel;

beforeEach(function () {
    $this->level = new KompetensiLevel;
});

it('mengklasifikasikan Mahir untuk theta >= 1.5 (7.5)', function () {
    expect($this->level->levelFor(2.0))->toBe(KompetensiLevel::MAHIR)
        ->and($this->level->levelFor(1.5))->toBe(KompetensiLevel::MAHIR);
});

it('mengklasifikasikan Menengah untuk 0.5 <= theta < 1.5', function () {
    expect($this->level->levelFor(1.0))->toBe(KompetensiLevel::MENENGAH)
        ->and($this->level->levelFor(1.499))->toBe(KompetensiLevel::MENENGAH)
        ->and($this->level->levelFor(0.5))->toBe(KompetensiLevel::MENENGAH);
});

it('mengklasifikasikan Dasar untuk -0.5 <= theta < 0.5', function () {
    expect($this->level->levelFor(0.0))->toBe(KompetensiLevel::DASAR)
        ->and($this->level->levelFor(0.499))->toBe(KompetensiLevel::DASAR)
        ->and($this->level->levelFor(-0.5))->toBe(KompetensiLevel::DASAR);
});

it('mengklasifikasikan Perlu Bimbingan untuk -1.5 <= theta < -0.5', function () {
    expect($this->level->levelFor(-1.0))->toBe(KompetensiLevel::PERLU_BIMBINGAN)
        ->and($this->level->levelFor(-0.501))->toBe(KompetensiLevel::PERLU_BIMBINGAN)
        ->and($this->level->levelFor(-1.5))->toBe(KompetensiLevel::PERLU_BIMBINGAN);
});

it('mengklasifikasikan Belum Teridentifikasi untuk theta < -1.5', function () {
    expect($this->level->levelFor(-1.6))->toBe(KompetensiLevel::BELUM_TERIDENTIFIKASI)
        ->and($this->level->levelFor(-3.0))->toBe(KompetensiLevel::BELUM_TERIDENTIFIKASI);
});

it('theta bernilai null dianggap Belum Teridentifikasi', function () {
    expect($this->level->levelFor(null))->toBe(KompetensiLevel::BELUM_TERIDENTIFIKASI);
});

it('menyediakan label Bahasa Indonesia per level', function () {
    expect($this->level->label(KompetensiLevel::MAHIR))->toBe('Mahir')
        ->and($this->level->label(KompetensiLevel::MENENGAH))->toBe('Menengah')
        ->and($this->level->label(KompetensiLevel::DASAR))->toBe('Dasar')
        ->and($this->level->label(KompetensiLevel::PERLU_BIMBINGAN))->toBe('Perlu Bimbingan')
        ->and($this->level->label(KompetensiLevel::BELUM_TERIDENTIFIKASI))->toBe('Belum Teridentifikasi');
});

it('label untuk level tak dikenal mengembalikan input apa adanya', function () {
    expect($this->level->label('bukan_level'))->toBe('bukan_level');
});

it('memberi rekomendasi latihan sesuai theta pada tiap level (3.9)', function () {
    expect($this->level->rekomendasiFor(2.0))
        ->toBe('Pertahankan, KD ini sudah Mahir. Tantang dirimu dengan soal lebih sulit.')
        ->and($this->level->rekomendasiFor(1.0))
        ->toBe('Lanjutkan latihan KD ini agar naik ke Mahir.')
        ->and($this->level->rekomendasiFor(0.0))
        ->toBe('Terus latihan KD ini agar naik ke Menengah.')
        ->and($this->level->rekomendasiFor(-1.0))
        ->toBe('Fokus latihan KD ini karena masih Perlu Bimbingan.')
        ->and($this->level->rekomendasiFor(-3.0))
        ->toBe('Belum ada data untuk KD ini. Kerjakan latihan agar kompetensimu teridentifikasi.');
});

it('theta null direkomendasikan seperti belum teridentifikasi', function () {
    expect($this->level->rekomendasiFor(null))
        ->toBe($this->level->rekomendasi(KompetensiLevel::BELUM_TERIDENTIFIKASI))
        ->and($this->level->rekomendasiFor(null))
        ->toBe('Belum ada data untuk KD ini. Kerjakan latihan agar kompetensimu teridentifikasi.');
});

it('rekomendasi untuk level tak dikenal mengembalikan input apa adanya', function () {
    expect($this->level->rekomendasi('bukan_level'))->toBe('bukan_level');
});
