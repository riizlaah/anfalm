<?php

use App\Domain\Konten\KontenSanitizer;

beforeEach(function () {
    $this->sanitizer = new KontenSanitizer;
});

it('meneruskan teks biasa apa adanya tanpa meng-encode karakter (6.12)', function (string $masukan) {
    expect($this->sanitizer->bersihkan($masukan))->toBe($masukan);
})->with([
    'pertanyaan' => ['Berapa hasil dari 2 + 2?'],
    'pernyataan baris' => ["Baris satu\nBaris dua"],
    'karakter khusus' => ['A & B < C'],
    'relasi' => ['karena i < n dan n > 0'],
    'kaTeX' => ['Rasio \( \frac{2}{3} \)'],
]);

it('mempertahankan markup aman yang dihasilkan TipTap (4.2)', function (string $masukan, string $diharapkan) {
    expect($this->sanitizer->bersihkan($masukan))->toBe($diharapkan);
})->with([
    ['<p>Hello <strong>world</strong></p>', '<p>Hello <strong>world</strong></p>'],
    ['<p><em>miring</em> <u>garis bawah</u> <s>coret</s></p>', '<p><em>miring</em> <u>garis bawah</u> <s>coret</s></p>'],
    ['<ul><li>a</li><li>b</li></ul>', '<ul><li>a</li><li>b</li></ul>'],
    ['<ol start="3"><li>c</li></ol>', '<ol start="3"><li>c</li></ol>'],
    ['<blockquote><p>kutipan</p></blockquote>', '<blockquote><p>kutipan</p></blockquote>'],
    ['<pre><code>kode</code></pre>', '<pre><code>kode</code></pre>'],
    ['<p>soal<br>baris dua</p>', '<p>soal<br />baris dua</p>'],
]);

it('membuang tag berbahaya dan atribut event handler (6.12)', function (string $masukan, string $harapan) {
    expect($this->sanitizer->bersihkan($masukan))->toBe($harapan);
})->with([
    ['<script>alert(1)</script><p>aman</p>', '<p>aman</p>'],
    ['<p onclick="evil()">hi</p>', '<p>hi</p>'],
    ['<img src="x" onerror="alert(1)">', '<img src="x" />'],
    ['<p>gaya</p><style>body{display:none}</style>', '<p>gaya</p>'],
    ['<iframe src="https://evil.test"></iframe><p>aman</p>', '<p>aman</p>'],
    ['<form action="https://evil.test"><input name="x"></form><p>aman</p>', '<p>aman</p>'],
    ['<a href="javascript:alert(1)">klik</a>', '<a>klik</a>'],
    ['<a href="https://aman.test" title="t">klik</a>', '<a href="https://aman.test" title="t">klik</a>'],
    ['<!-- komentar -->', ''],
]);

it('mempertahankan ekspresi KaTeX apa adanya (4.3)', function () {
    $inline = '<p>Rasio <span class="katex-inline">\( \frac{2}{3} \)</span></p>';

    expect($this->sanitizer->bersihkan($inline))->toBe($inline);
});

it('idempoten: sanitasi kedua kali tidak mengubah hasil', function (string $masukan) {
    $sekali = $this->sanitizer->bersihkan($masukan);

    expect($this->sanitizer->bersihkan($sekali))->toBe($sekali);
})->with([
    ['<p>Hello <strong>world</strong></p>'],
    ['Berapa hasil dari 2 + 2?'],
    ["Baris satu\nBaris dua"],
    ['<script>alert(1)</script><p>aman</p>'],
    ['<script>y</script>Opsi A'],
    ['<script>y</script>'],
    ['<!-- komentar -->'],
]);

it('meneruskan null dan string kosong tanpa diubah', function () {
    expect($this->sanitizer->bersihkan(null))->toBeNull()
        ->and($this->sanitizer->bersihkan(''))->toBe('')
        ->and($this->sanitizer->bersihkan('   '))->toBe('   ');
});

it('mempertahankan gambar lokal relatif', function () {
    expect($this->sanitizer->bersihkan('<img src="/storage/gambar/soal.webp" alt="soal" width="300">'))
        ->toBe('<img src="/storage/gambar/soal.webp" alt="soal" width="300" />');
});

it('untukTampilan meng-escape teks biasa sekaligus memecah barisnya', function () {
    expect($this->sanitizer->untukTampilan("Baris satu\nBaris dua"))
        ->toBe('Baris satu<br />'."\n".'Baris dua')
        ->and($this->sanitizer->untukTampilan('A & B < C'))
        ->toBe('A &amp; B &lt; C');
});

it('untukTampilan menyaring HTML sebelum dirender mentah', function () {
    expect($this->sanitizer->untukTampilan('<p>aman <strong>ini</strong></p><script>alert(1)</script>'))
        ->toBe('<p>aman <strong>ini</strong></p>');
});

it('untukTampilan mengembalikan string kosong untuk null', function () {
    expect($this->sanitizer->untukTampilan(null))->toBe('')
        ->and($this->sanitizer->untukTampilan(''))->toBe('');
});
