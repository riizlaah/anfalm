<?php

/**
 * Gaya blok kode dan kode inline di luar editor (butir 164, REPORT_N_SUGGEST.md).
 *
 * Editor sudah punya aturannya sendiri (`.wysiwyg-kolom-wadah .tiptap pre`), tetapi blok
 * kode yang sama juga muncul di tabel manajemen soal dan di halaman peserta. Selama gayanya
 * hanya dibuat untuk `.tiptap`, konten di dua tempat itu tetap memakai gaya bawaan browser:
 * baris kode panjang menabrak tata letak sel dan kode inline memaksa sel melebar.
 */

/**
 * Ambil isi satu aturan dari `app.css` berdasarkan pemilihnya.
 *
 * Pemilih dicocokkan dari awal baris sehingga aturan lama yang membungkus
 * `.wysiwyg-kolom-wadah .tiptap pre` tidak pernah dianggap memadai.
 */
function isiAturan(string $pemilih): string
{
    $css = file_get_contents(resource_path('css/app.css'));

    return preg_match('/^[ \t]*'.$pemilih.'[ \t]*\{([^}]*)\}/m', $css, $cocok) === 1 ? $cocok[1] : '';
}

it('gaya blok kode tetap terbaca tanpa melebarkan sel yang memuatnya', function () {
    expect(isiAturan('pre'))
        // Baris kode baru tetap terjaga, tapi token yang kepanjangan ikut membungkus
        // diri sehingga tata letak tabel tidak dipaksa melebar oleh isinya.
        ->toContain('whitespace-pre-wrap')
        ->toContain('overflow-wrap')
        ->toContain('font-mono')
        // `overflow-x` wajib tetap `visible`: selama `pre` jadi scroll container,
        // `-webkit-line-clamp` milik preview tabel berhenti memotong isinya.
        ->not->toContain('overflow-x');
});

it('gaya kode inline membungkus diri di sel yang sempit', function () {
    expect(isiAturan('code'))
        ->toContain('whitespace-pre-wrap')
        ->toContain('overflow-wrap')
        ->toContain('font-mono');
});
