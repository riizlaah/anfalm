<?php

use App\Domain\Ai\MarkdownKeHtml;

it('mengubah code fence menjadi blok kode pada konteks isi soal', function () {
    $hasil = (new MarkdownKeHtml)->konversiBlok(<<<'MD'
Perhatikan potongan berikut:

```js
console.log("halo")
```

Apa outputnya?
MD);

    expect($hasil)
        ->toContain('<pre><code>console.log(&quot;halo&quot;)</code></pre>')
        ->toContain('<p>Apa outputnya?</p>')
        ->not->toContain('```');
});

it('mengubah code fence satu baris yang penulisannya menyimpang menjadi kode', function () {
    // AI kadang menulis fence tanpa baris baru dan dengan titik pada info string,
    // sehingga pola "bahasa lalu isi" biasa tidak akan cocok.
    $hasil = (new MarkdownKeHtml)->konversiBlok('Keluarannya ```console.log("halo")``` sesuai harapan.');

    expect($hasil)->toContain('<code>console.log(&quot;halo&quot;)</code>');
});

it('code fence menjadi kode inline pada konteks opsi jawaban', function () {
    $hasil = (new MarkdownKeHtml)->konversiInline("Hasilnya\n```x = 2```\nbetul");

    expect($hasil)
        ->toContain('<code>x = 2</code>')
        ->not->toContain('<pre')
        ->not->toContain('```');
});

it('isi kode tidak ikut diolah sebagai markdown', function () {
    $hasil = (new MarkdownKeHtml)->konversiBlok('Gunakan `**bukan tebal**` di sini.');

    expect($hasil)
        ->toContain('<code>**bukan tebal**</code>')
        ->not->toContain('<strong>');
});

it('menambah tebal dan miring pada teks biasa', function () {
    $hasil = (new MarkdownKeHtml)->konversiBlok('Ini **penting** dan *sekali* saja.');

    expect($hasil)
        ->toContain('<strong>penting</strong>')
        ->toContain('<em>sekali</em>');
});

it('mengubah daftar tak bernomor dan bernomor', function () {
    $hasil = (new MarkdownKeHtml)->konversiBlok("Langkahnya:\n\n- siapkan alat\n- ukur panjang");

    expect($hasil)->toContain('<ul><li>siapkan alat</li><li>ukur panjang</li></ul>');

    $urut = (new MarkdownKeHtml)->konversiBlok("Urutannya:\n\n1. buka tutup\n2. isi air");

    expect($urut)->toContain('<ol><li>buka tutup</li><li>isi air</li></ol>');
});

it('mengubah judul deret ketiga ke tiga', function () {
    $hasil = (new MarkdownKeHtml)->konversiBlok("## Bilangan\n\nIsi paragraf.");

    expect($hasil)
        ->toContain('<h2>Bilangan</h2>')
        ->toContain('<p>Isi paragraf.</p>')
        ->not->toContain('## ');
});

it('judul dan daftar tidak diubah pada konteks opsi jawaban', function () {
    $inline = new MarkdownKeHtml;

    expect($inline->konversiInline('## Bilangan'))->toBe('## Bilangan');
    expect($inline->konversiInline("- satu\n- dua"))->not->toContain('<li>');
});

it('menjaga ekspresi LaTeX tetap utuh', function () {
    $sumber = 'Hasil dari \( \frac{2}{3} \) adalah setengah.';
    $hasil = (new MarkdownKeHtml)->konversiBlok($sumber);

    expect($hasil)->toBe($sumber);
});

it('perkalian berbintang tidak dianggap teks miring', function () {
    $sumber = 'Hasil dari 2*3*4 sama dengan 24.';
    $hasil = (new MarkdownKeHtml)->konversiBlok($sumber);

    expect($hasil)->toBe($sumber);
});

it('meneruskan null dan string kosong apa adanya', function () {
    $konverter = new MarkdownKeHtml;

    expect($konverter->konversiBlok(null))->toBeNull();
    expect($konverter->konversiInline(''))->toBe('');
});

it('melewatkan konten yang sudah berupa HTML', function () {
    $sumber = '<p>Sudah <strong>HTML</strong> sejak awal.</p>';
    $konverter = new MarkdownKeHtml;

    expect($konverter->konversiBlok($sumber))->toBe($sumber);
    expect($konverter->konversiInline($sumber))->toBe($sumber);
});

it('menyaring tag dari isi kode', function () {
    $hasil = (new MarkdownKeHtml)->konversiBlok("```php\n<script>alert(1)</script>\n```");

    expect($hasil)->toContain('&lt;script&gt;')->not->toContain('<script>');
});

it('konversi kedua kali memberi hasil yang sama', function () {
    $konverter = new MarkdownKeHtml;
    $sekali = $konverter->konversiBlok("## Judul\n\n- satu\n- dua\n\nTeks **tebal**.");

    expect($konverter->konversiBlok($sekali))->toBe($sekali);
});

it('teks biasa tanpa tanda markdown tidak diubah sama sekali', function () {
    $sumber = "Hasil dari 2 + 2 adalah 4.\nBaris kedua penjelasan.";
    $konverter = new MarkdownKeHtml;

    expect($konverter->konversiBlok($sumber))->toBe($sumber);
    expect($konverter->konversiInline($sumber))->toBe($sumber);
});
