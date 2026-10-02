<?php

use App\Domain\Ai\PromptBuilder;

it('mendistribusikan jumlah soal merata ke semua KD', function () {
    $kds = [
        ['kode' => '3.1', 'deskripsi' => 'Bilangan berpangkat'],
        ['kode' => '3.2', 'deskripsi' => 'Bentuk akar'],
        ['kode' => '3.3', 'deskripsi' => 'Logaritma'],
    ];

    $prompt = (new PromptBuilder)->build($kds, 'Matematika', 20, 'campuran');

    expect($prompt)
        ->toContain('Buatkan 20 soal')
        ->toContain('(target: 7 soal)')
        ->toContain('(target: 6 soal)')
        ->toContain('tepat 20 objek soal');
});

it('memberikan seluruh soal pada satu KD tunggal', function () {
    $prompt = (new PromptBuilder)->build([['kode' => '3.1', 'deskripsi' => 'KD A']], 'Matematika', 12, 'mudah');

    expect($prompt)
        ->toContain('Buatkan 12 soal')
        ->toContain('(target: 12 soal)');
});

it('menyertakan referensi hanya bila diberikan', function () {
    $kds = [['kode' => '3.1', 'deskripsi' => 'KD A']];

    $tanpaReferensi = (new PromptBuilder)->build($kds, 'Matematika', 5, 'campuran');
    $denganReferensi = (new PromptBuilder)->build($kds, 'Matematika', 5, 'campuran', 'Materi bab 2 halaman 10-20.');

    expect($tanpaReferensi)->not->toContain('Referensi tambahan');
    expect($denganReferensi)
        ->toContain('Referensi tambahan')
        ->toContain('Materi bab 2 halaman 10-20.');
});

it('mencantumkan aturan tingkat kesulitan sesuai permintaan', function () {
    $kds = [['kode' => '3.1', 'deskripsi' => 'KD A']];

    $prompt = (new PromptBuilder)->build($kds, 'Matematika', 5, 'sulit');

    expect($prompt)
        ->toContain('Estimasi parameter mengikuti tingkat kesulitan: sulit')
        ->toContain('Jika "sulit": semua soal b > 0.');
});

it('menolak jumlah soal kurang dari 1 dan daftar KD kosong', function () {
    $builder = new PromptBuilder;

    expect(fn () => $builder->build([['kode' => '3.1', 'deskripsi' => 'x']], 'Mat', 0, 'campuran'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $builder->build([], 'Mat', 5, 'campuran'))
        ->toThrow(InvalidArgumentException::class);
});

it('memakai target dari penjadwal alih-alih membagi merata sendiri', function () {
    $kds = [
        ['kode' => '3.1', 'deskripsi' => 'Bilangan berpangkat'],
        ['kode' => '3.2', 'deskripsi' => 'Bentuk akar'],
        ['kode' => '3.3', 'deskripsi' => 'Logaritma'],
        ['kode' => '3.4', 'deskripsi' => 'Barisan'],
    ];

    $prompt = (new PromptBuilder)->build($kds, 'Matematika', 6, 'campuran', null, [
        '3.1' => 3,
        '3.2' => 1,
        '3.3' => 1,
        '3.4' => 1,
    ]);

    expect($prompt)
        ->toContain('Buatkan 6 soal')
        ->toContain('KD 3.1 - Bilangan berpangkat (target: 3 soal)')
        ->toContain('KD 3.2 - Bentuk akar (target: 1 soal)')
        ->toContain('KD 3.3 - Logaritma (target: 1 soal)')
        ->toContain('KD 3.4 - Barisan (target: 1 soal)');
});

it('menggugurkan KD yang tidak menerima kuota pada part ini', function () {
    $kds = [
        ['kode' => '1.1', 'deskripsi' => 'KD depan'],
        ['kode' => '1.2', 'deskripsi' => 'KD ekor'],
    ];

    $prompt = (new PromptBuilder)->build($kds, 'Informatika', 4, 'campuran', null, ['1.1' => 4]);

    expect($prompt)
        ->toContain('KD 1.1 - KD depan (target: 4 soal)')
        ->and($prompt)->not->toContain('KD 1.2 - KD ekor')
        ->and($prompt)->not->toContain('target: 0 soal');
});

it('menolak peta target yang tidak menugaskan soal apa pun', function () {
    $kds = [['kode' => '3.1', 'deskripsi' => 'KD A']];

    expect(fn () => (new PromptBuilder)->build($kds, 'Matematika', 5, 'campuran', null, []))
        ->toThrow(InvalidArgumentException::class);
});
