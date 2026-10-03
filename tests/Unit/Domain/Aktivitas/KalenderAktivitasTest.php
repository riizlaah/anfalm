<?php

use App\Domain\Aktivitas\KalenderAktivitas;
use Carbon\Carbon;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->kalender = new KalenderAktivitas;

    // Sabtu, sehingga minggu berjalan masih menyisakan hari Minggu di depan.
    $this->hariIni = Carbon::create(2026, 10, 3);
});

/**
 * Seluruh sel delapan minggu dalam satu daftar datar, disusun per minggu.
 *
 * @return Collection<int, array{tanggal: string, jumlah: int, tingkat: int}>
 */
function selKalender(array $hasil): Collection
{
    return collect($hasil['minggu'])->collapse();
}

it('menghentikan streak pada hari yang kosong', function () {
    $hasil = $this->kalender->susun([
        '2026-10-03' => 1,
        '2026-10-02' => 1,
        '2026-09-30' => 1,
    ], $this->hariIni);

    expect($hasil['streak'])->toBe(2);
});

it('mempertahankan streak sampai kemarin ketika hari ini belum diisi', function () {
    $hasil = $this->kalender->susun([
        '2026-10-02' => 1,
        '2026-10-01' => 1,
        '2026-09-30' => 1,
    ], $this->hariIni);

    expect($hasil['streak'])->toBe(3);
});

it('menghitung nol untuk streak maupun hari aktif ketika aktivitasnya sudah di luar jendela', function () {
    // 15 dan 10 Juli berada sebelum jendela yang dimulai 10 Agustus, sekaligus
    // tidak berdampingan: tidak ada yang bisa dihitung sama sekali.
    $hasil = $this->kalender->susun([
        '2026-07-15' => 4,
        '2026-07-10' => 2,
    ], $this->hariIni);

    expect($hasil['streak'])->toBe(0)
        ->and($hasil['hari_aktif'])->toBe(0);
});

it('menyusun delapan minggu sel penuh dari Senin hingga hari Minggu', function () {
    $hasil = $this->kalender->susun(['2026-10-03' => 1], $this->hariIni);

    $sel = selKalender($hasil);

    expect($hasil['minggu'])->toHaveCount(8)
        ->and($sel)->toHaveCount(56)
        ->and($sel->first()['tanggal'])->toBe('2026-08-10')
        ->and($sel->last()['tanggal'])->toBe('2026-10-04')
        ->and($hasil['hari_aktif'])->toBe(1);
});

it('menaikkan tingkat sel mengikuti jumlah aktivitas hari itu', function () {
    $hasil = $this->kalender->susun([
        '2026-10-03' => 1,
        '2026-10-02' => 4,
        '2026-10-01' => 9,
    ], $this->hariIni);

    $sel = selKalender($hasil)->keyBy('tanggal');

    expect($sel['2026-10-03']['tingkat'])->toBe(1)
        ->and($sel['2026-10-02']['tingkat'])->toBe(2)
        ->and($sel['2026-10-01']['tingkat'])->toBe(3)
        ->and($sel['2026-09-30']['tingkat'])->toBe(0);
});
