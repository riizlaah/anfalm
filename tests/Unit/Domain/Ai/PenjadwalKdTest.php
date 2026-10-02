<?php

use App\Domain\Ai\PenjadwalKd;

/**
 * Menjalankan satu sesi generate: part demi part sampai target terkumpul,
 * menghitung sendiri berapa soal yang benar-benar dihasilkan tiap part
 * (yield bisa kurang dari yang diminta, seperti perilaku AI aslinya).
 *
 * @param  callable(int): int  $yieldAktual
 * @return array<string, int> kode KD => jumlah soal yang benar-benar ada
 */
function jalankanSesiPenjadwal(int $totalSoal, array $kodeKd, int $perPart, callable $yieldAktual): array
{
    $jadwal = new PenjadwalKd;
    $terpakai = [];
    $akumulasi = 0;

    while ($akumulasi < $totalSoal) {
        $jumlahBagian = min($perPart, $totalSoal - $akumulasi);
        $part = $jadwal->targetPart($totalSoal, $kodeKd, $terpakai, $jumlahBagian);

        expect($part)->not->toBe([]);

        $diminta = array_sum(array_column($part, 'target'));
        $aktual = max(0, min($diminta, $yieldAktual($diminta)));

        // Hanya soal yang benar-benar terhasilkan yang dicatat, dan kekurangannya
        // ditimpakan pada KD terakhir supaya jumlahnya selalu sama dengan akumulasi
        // — persis seperti data `ai_parts` yang dibaca penjadwal di aplikasi.
        $sisaKurang = $diminta - $aktual;

        foreach (array_reverse($part) as $baris) {
            $potong = min($baris['target'], $sisaKurang);
            $target = $baris['target'] - $potong;
            $sisaKurang -= $potong;

            if ($target > 0) {
                $terpakai[$baris['kode']] = ($terpakai[$baris['kode']] ?? 0) + $target;
            }
        }

        $akumulasi += $aktual;
    }

    return $terpakai;
}

it('membagi target seluruh paket merata ke semua KD', function () {
    $target = (new PenjadwalKd)->targetGlobal(30, ['3.1', '3.2', '3.3']);

    expect($target)->toBe([
        ['kode' => '3.1', 'target' => 10],
        ['kode' => '3.2', 'target' => 10],
        ['kode' => '3.3', 'target' => 10],
    ]);
});

it('menambah satu soal pada KD di depan bila target tidak habis dibagi', function () {
    $target = (new PenjadwalKd)->targetGlobal(4, ['3.1', '3.2', '3.3']);

    expect($target)->toBe([
        ['kode' => '3.1', 'target' => 2],
        ['kode' => '3.2', 'target' => 1],
        ['kode' => '3.3', 'target' => 1],
    ]);
});

it('part pertama hanya memuat KD yang masih punya kuota, tanpa target nol', function () {
    $part = (new PenjadwalKd)->targetPart(30, [
        '1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9', '1.10',
    ], [], 6);

    // Kuota global 3 per KD, tapi part cuma 6 soal: enam KD terdepan masing-
    // masing satu soal. Sisanya sengaja tidak ikut, bukan ikut dengan target nol.
    expect($part)->toHaveCount(6)
        ->and(array_column($part, 'target'))->toBe([1, 1, 1, 1, 1, 1])
        ->and(array_column($part, 'kode'))->toBe(['1.1', '1.2', '1.3', '1.4', '1.5', '1.6']);
});

it('membagi satu part ke banyak KD daripada menumpuk di satu KD', function () {
    $part = (new PenjadwalKd)->targetPart(30, ['3.1', '3.2', '3.3', '3.4'], [], 6);

    // Kuota global 8/8/7/7; enam soalnya dibagi bergiliran supaya prompt tidak
    // meminta enam soal berturut-turut pada satu KD yang sama.
    expect($part)->toBe([
        ['kode' => '3.1', 'target' => 2],
        ['kode' => '3.2', 'target' => 2],
        ['kode' => '3.3', 'target' => 1],
        ['kode' => '3.4', 'target' => 1],
    ]);
});

it('meneruskan ke KD berikutnya setelah kuota KD di depannya terpenuhi', function () {
    $jadwal = new PenjadwalKd;
    $kodeKd = ['3.1', '3.2', '3.3', '3.4'];

    $kedua = $jadwal->targetPart(12, $kodeKd, ['3.1' => 3, '3.2' => 3], 6);

    expect($kedua)->toBe([
        ['kode' => '3.3', 'target' => 3],
        ['kode' => '3.4', 'target' => 3],
    ]);
});

/**
 * Kuota global sebagai peta kode => target, diurutkan berdasarkan kode karena
 * `jalankanSesiPenjadwal` menumpuk hasilnya menurut urutan penjadwalan.
 *
 * @param  list<string>  $kodeKd
 * @return array<string, int>
 */
function distribusiGlobalDiinginkan(int $totalSoal, array $kodeKd): array
{
    $peta = collect((new PenjadwalKd)->targetGlobal($totalSoal, $kodeKd))
        ->pluck('target', 'kode')
        ->all();

    ksort($peta);

    return $peta;
}

it('menutup distribusi global persis setelah seluruh part dijalankan', function () {
    $kodeKd = ['3.1', '3.2', '3.3', '3.4', '3.5'];
    $total = 30;

    $terpakai = jalankanSesiPenjadwal($total, $kodeKd, 6, fn (int $diminta): int => $diminta);

    ksort($terpakai);

    expect($terpakai)->toBe(distribusiGlobalDiinginkan($total, $kodeKd));
});

it('tetap menutup distribusi global walau tiap part menghasilkan soal kurang', function () {
    $kodeKd = ['3.1', '3.2', '3.3', '3.4', '3.5'];
    $total = 30;

    // Part pertama hanya menghasilkan setengahnya; sisa ditampung part berikut.
    $terpakai = jalankanSesiPenjadwal($total, $kodeKd, 6, function (int $diminta): int {
        static $panggilan = 0;

        return ++$panggilan === 1 ? (int) ceil($diminta / 2) : $diminta;
    });

    $diinginkan = distribusiGlobalDiinginkan($total, $kodeKd);

    ksort($terpakai);

    expect($terpakai)->toBe($diinginkan);
});

it('menjangkau seluruh KD selama jumlah soal memadai', function () {
    $kodeKd = ['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9', '1.10'];
    $total = 30;

    $terpakai = jalankanSesiPenjadwal($total, $kodeKd, 6, fn (int $diminta): int => $diminta);

    expect(array_keys($terpakai))->toEqualCanonicalizing($kodeKd)
        ->and(collect($terpakai)->min())->toBeGreaterThan(0);
});

it('menjumlahkan hasilnya persis sebesar jumlah bagian', function () {
    $jadwal = new PenjadwalKd;
    $kasus = [
        ['total' => 30, 'kode' => ['1.1', '1.2', '1.3'], 'bagian' => 6, 'terpakai' => []],
        ['total' => 12, 'kode' => ['3.1', '3.2', '3.3', '3.4'], 'bagian' => 6, 'terpakai' => ['3.1' => 3]],
        ['total' => 7, 'kode' => ['2.1', '2.2', '2.3'], 'bagian' => 2, 'terpakai' => ['2.1' => 3]],
    ];

    foreach ($kasus as $k) {
        $part = $jadwal->targetPart($k['total'], $k['kode'], $k['terpakai'], $k['bagian']);

        expect(array_sum(array_column($part, 'target')))->toBe($k['bagian'])
            ->and(array_column($part, 'target'))->each->toBeGreaterThan(0);
    }
});

it('menghasilkan daftar kosong bila tidak ada KD atau tanpa sisa', function () {
    $jadwal = new PenjadwalKd;

    expect($jadwal->targetPart(10, [], [], 5))->toBe([])
        ->and($jadwal->targetPart(10, ['3.1'], [], 0))->toBe([]);
});

it('mengabaikan jumlah terpakai milik KD di luar pilihan', function () {
    $part = (new PenjadwalKd)->targetPart(12, ['3.1', '3.2'], ['9.9' => 20], 6);

    expect($part)->toBe([
        ['kode' => '3.1', 'target' => 3],
        ['kode' => '3.2', 'target' => 3],
    ]);
});

it('hasil penjadwalan tidak dipengaruhi urutan masukan jumlah terpakai', function () {
    $kodeKd = ['3.1', '3.2', '3.3', '3.4'];

    $urut = (new PenjadwalKd)->targetPart(12, $kodeKd, ['3.1' => 3, '3.2' => 1], 6);
    $acak = (new PenjadwalKd)->targetPart(12, $kodeKd, ['3.2' => 1, '3.1' => 3], 6);

    expect($urut)->toBe($acak);
});
