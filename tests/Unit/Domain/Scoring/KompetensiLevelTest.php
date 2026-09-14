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
