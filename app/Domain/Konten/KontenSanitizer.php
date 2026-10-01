<?php

namespace App\Domain\Konten;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Menjaga konten soal tetap aman saat disimpan dan saat ditampilkan (DESIGN §6.12).
 *
 * Konten dalam aplikasi ini ada dalam dua wujud:
 *
 * - **Teks biasa** — ditulis lewat textarea lama atau seeder. Tidak mengandung tag
 *   sama sekali, sehingga tidak perlu disaring dan tidak boleh diubah-ubah: mengirimkannya
 *   lewat sanitizer akan meng-encode `+`, `=`, `@`, dan `` ` `` menjadi entitas HTML
 *   sehingga soal matematika tersimpan sebagai `2 &#43; 2`.
 * - **HTML** — dihasilkan TipTap. Disaring sebelum disimpan dan disaring lagi sebelum
 *   ditampilkan, sehingga markup aman yang disimpan di database pun tetap tidak
 *   pernah dilepas mentah-mentah ke halaman.
 *
 * Pembedanya adalah keberadaan tag HTML; lihat {@see TAG_HTML}.
 */
class KontenSanitizer
{
    /**
     * Pola pembuka tag HTML: `<` diikuti huruf, garis miring, atau tanda seru
     * (`<p`, `</p`, `<!--`). Teks biasa berisi `<` (mis. `A < C`, `i < n`)
     * tidak cocok dan karena itu diperlakukan sebagai teks biasa.
     */
    private const TAG_HTML = '/<(?:[a-z!\/])/i';

    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('ul')
            ->allowElement('ol', ['start'])
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('pre')
            ->allowElement('code', ['class'])
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('hr')
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowElement('span', ['class'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withMaxInputLength(65_000);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Saring konten sebelum disimpan. Teks biasa diteruskan apa adanya.
     */
    public function bersihkan(?string $isi): ?string
    {
        if ($isi === null || trim($isi) === '' || ! $this->adaTag($isi)) {
            return $isi;
        }

        return $this->sanitizer->sanitize($isi);
    }

    /**
     * Hasil aman untuk dirender mentah oleh view (`{!! !!}`).
     *
     * HTML disaring; teks biasa di-escape dan pergantian barisnya dijadikan `<br />`
     * sehingga tampil identik dengan perilaku `nl2br(e(...))` yang lama.
     */
    public function untukTampilan(?string $isi): string
    {
        if ($isi === null || $isi === '') {
            return '';
        }

        if ($this->adaTag($isi)) {
            return $this->sanitizer->sanitize($isi);
        }

        return nl2br(e($isi));
    }

    private function adaTag(string $isi): bool
    {
        return preg_match(self::TAG_HTML, $isi) === 1;
    }

    /**
     * Apakah konten memuat teks yang terlihat atau sekurangnya satu gambar?
     *
     * Dipakai validasi untuk menolak editor WYSIWYG yang dikirim kosong: TipTap
     * selalu menghasilkan `<p></p>` untuk dokumen kosong, sehingga aturan
     * `required` saja tidak cukup. Pemotongan tagnya memakai pola yang sama
     * dengan {@see self::TAG_HTML} agar `i < n` tidak ikut terpotong.
     */
    public function adaIsi(?string $isi): bool
    {
        if ($isi === null) {
            return false;
        }

        if (str_contains($isi, '<img')) {
            return true;
        }

        return trim((string) preg_replace('/<\/?[a-z][^>]*>/i', '', $isi)) !== '';
    }
}
