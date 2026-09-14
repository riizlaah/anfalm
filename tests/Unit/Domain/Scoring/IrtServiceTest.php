<?php

use App\Domain\Scoring\IrtService;

function assertCloseTo(float $actual, float $expected, float $delta = 1e-4): void
{
    expect(abs($actual - $expected))->toBeLessThanOrEqual($delta);
}

beforeEach(function () {
    $this->irt = new IrtService;
});

it('menghitung probabilitas 3PL di theta 0', function () {
    assertCloseTo($this->irt->probability(0.0, 1.0, 0.0, 0.25), 0.625, 1e-6);
});

it('probabilitas mendekati 1 untuk theta sangat tinggi', function () {
    assertCloseTo($this->irt->probability(10.0, 1.0, 0.0, 0.25), 1.0, 1e-4);
});

it('probabilitas mendekati c (tebakan) untuk theta sangat rendah', function () {
    assertCloseTo($this->irt->probability(-10.0, 1.0, 0.0, 0.25), 0.25, 1e-4);
});

it('probabilitas tanpa tebakan (c=0) di theta 0 adalah 0.5', function () {
    assertCloseTo($this->irt->probability(0.0, 1.0, 0.0, 0.0), 0.5, 1e-6);
});

it('probabilitas bersifat monoton naik terhadap theta', function () {
    expect($this->irt->probability(1.0, 1.0, 0.0, 0.0))
        ->toBeGreaterThan($this->irt->probability(0.0, 1.0, 0.0, 0.0));
});

it('menghasilkan default IRT konsisten', function () {
    expect(IrtService::DEFAULT_A)->toBe(1.0)
        ->and(IrtService::DEFAULT_B)->toBe(0.0)
        ->and(IrtService::DEFAULT_C)->toBe(0.25);
});

it('MLE theta untuk items kosong adalah 0', function () {
    expect($this->irt->estimateMle([]))->toBe(0.0);
});

it('MLE semua jawaban benar diberi theta ekstrem 3.0 (edge 6.3)', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'response' => 1],
        ['a' => 1.2, 'b' => -0.5, 'c' => 0.2, 'response' => 1],
    ];
    expect($this->irt->estimateMle($items))->toBe(3.0);
});

it('MLE semua jawaban salah diberi theta ekstrem -3.0 (edge 6.3)', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'response' => 0],
    ];
    expect($this->irt->estimateMle($items))->toBe(-3.0);
});

it('MLE [benar-salah] dengan c=0.25 menghasilkan theta = -ln 2', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'response' => 1],
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'response' => 0],
    ];
    assertCloseTo($this->irt->estimateMle($items), -log(2), 1e-3);
});

it('MLE [benar-salah] tanpa tebakan menghasilkan theta 0', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.0, 'response' => 1],
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.0, 'response' => 0],
    ];
    assertCloseTo($this->irt->estimateMle($items), 0.0, 1e-4);
});

it('MLE menjawab seluruh item dengan pola sama menghasilkan nilai ekstrem', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.0, 'response' => 1],
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.0, 'response' => 1],
    ];
    expect($this->irt->estimateMle($items))->toBe(3.0);
});

it('MLE dengan prior (data sedikit) menghasilkan theta hingga, bukan ekstrem', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'response' => 1],
    ];
    $theta = $this->irt->estimateWithPrior($items, ['mean' => 0.0, 'sd' => 1.0]);

    expect(is_finite($theta))->toBeTrue()
        ->and(($theta > 0.0 && $theta < 3.0))->toBeTrue();
});

it('MLE dengan prior untuk items kosong mengembalikan mean prior', function () {
    assertCloseTo($this->irt->estimateWithPrior([], ['mean' => 0.5, 'sd' => 1.0]), 0.5, 1e-5);
});

it('standard error di theta 0 untuk a=1,b=0,c=0 adalah 2.0', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.0, 'response' => 1],
    ];
    assertCloseTo($this->irt->standardError(0.0, $items), 2.0, 1e-4);
});

it('standard error mengecil dengan informasi lebih banyak', function () {
    $satu = [['a' => 1.0, 'b' => 0.0, 'c' => 0.0, 'response' => 1]];
    $dua = array_merge($satu, $satu);

    expect($this->irt->standardError(0.0, $dua))
        ->toBeLessThan($this->irt->standardError(0.0, $satu));
});

it('standard error untuk c=0.25, a=1, b=0 di theta 0', function () {
    $items = [
        ['a' => 1.0, 'b' => 0.0, 'c' => 0.25, 'response' => 1],
    ];
    assertCloseTo($this->irt->standardError(0.0, $items), 2.581989, 1e-3);
});

it('standard error mengembalikan null ketika tidak ada item', function () {
    expect($this->irt->standardError(0.0, []))->toBeNull();
});

it('konversi skala SD: theta 0 menjadi 50', function () {
    expect($this->irt->convertToScale(0.0, 'SD'))->toBe(50);
});

it('konversi skala SD mengikuti rumus 50 + 10*theta', function () {
    expect($this->irt->convertToScale(0.5, 'SD'))->toBe(55)
        ->and($this->irt->convertToScale(3.0, 'SD'))->toBe(80)
        ->and($this->irt->convertToScale(-3.0, 'SD'))->toBe(20);
});

it('konversi skala SD di-clamp ke 0-100 (edge 6.8)', function () {
    expect($this->irt->convertToScale(10.0, 'SD'))->toBe(100)
        ->and($this->irt->convertToScale(-10.0, 'SD'))->toBe(0);
});

it('konversi skala SMP memakai skala 0-100 yang sama dengan SD', function () {
    expect($this->irt->convertToScale(0.0, 'SMP'))->toBe(50)
        ->and($this->irt->convertToScale(-8.0, 'SMP'))->toBe(0);
});

it('konversi skala SMA/SMK: theta 0 menjadi 450', function () {
    expect($this->irt->convertToScale(0.0, 'SMA'))->toBe(450)
        ->and($this->irt->convertToScale(0.0, 'SMK'))->toBe(450);
});

it('konversi skala SMA mengikuti rumus 450 + 100*theta', function () {
    expect($this->irt->convertToScale(1.0, 'SMA'))->toBe(550)
        ->and($this->irt->convertToScale(-1.0, 'SMA'))->toBe(350);
});

it('konversi skala SMA/SMK di-clamp ke 200-700 (edge 6.8)', function () {
    expect($this->irt->convertToScale(10.0, 'SMK'))->toBe(700)
        ->and($this->irt->convertToScale(-10.0, 'SMK'))->toBe(200);
});

it('tingkat yang tidak dikenal diperlakukan sebagai skala 200-700', function () {
    expect($this->irt->convertToScale(0.0, null))->toBe(450);
});
