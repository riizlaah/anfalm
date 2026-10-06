<?php

namespace App\Domain\Ai;

/**
 * Mengubah tulisan markdown dasar hasil AI menjadi HTML yang bisa dimasukkan ke editor WYSIWYG.
 *
 * Prompt AI hanya melarang code block pada pembungkus JSON (lihat {@see PromptBuilder});
 * isi `pertanyaan`, `pembahasan`, dan opsi sama sekali tidak diatur, sehingga AI bebas
 * menulis ```` ``` ````, `**tebal**`, `# judul`, atau daftar berbutir — tulisan yang selama ini
 * ditampilkan TipTap apa adanya.
 *
 * Dua jalur disediakan karena dua konteks menyimpan hasilnya dengan wujud yang berbeda:
 *
 * - {@see self::konversiBlok()} — pertanyaan dan pembahasan; menghasilkan struktur blok
 *   (`<p>`, `<pre>`, `<ul>`, `<h2>`).
 * - {@see self::konversiInline()} — opsi jawaban dan pernyataan; hanya menghasilkan konten
 *   frasa (`<code>`, `<strong>`, `<em>`, `<br>`) karena keduanya dirender di dalam `<label>`
 *   yang menurut spesifikasi HTML hanya boleh berisi konten frasa.
 *
 * Tiga jaminan yang dipegang seluruh metodenya:
 *
 * 1. Teks tanpa satu pun tanda markdown diteruskan persis seperti adanya, jadi soal biasa
 *    tidak ikut berubah bentuk simpannya dan `\( ... \)` tidak pernah tersentuh — semua pola
 *    bekerja pada tanda bintang, buhku, dan pagar, bukan pada garis miring balik.
 * 2. Konten yang sudah berupa HTML dilewatkan, sehingga konversi bisa dijalankan ulang tanpa
 *    merusak hasilnya.
 * 3. Isi kode disaring lebih dulu sebelum aturan lain bekerja, supaya `**bukan tebal**` di
 *    dalam kode tetap tertulis begitu saja.
 */
class MarkdownKeHtml
{
    /**
     * Tag yang dikenal aplikasi; kehadirannya menandai konten sudah berupa HTML.
     * Sengaja tag per tag, bukan pola `<` umum, supaya `n < m` di dalam soal matematika
     * tidak dianggap HTML dan menggagalkan konversi.
     */
    private const TAG_DIKENAL = '/<\/?(?:p|br|strong|b|em|i|u|s|sub|sup|ul|ol|li|blockquote|pre|code|h[1-6]|hr|a|img|span)(?=[\s>\/])/i';

    /**
     * @var list<string>
     */
    private const TANDA_MARKDOWN = [
        '/```/',
        '/`[^`\n]+`/',
        '/\*\*/',
        '/^#{1,6}[ \t]+/m',
        '/^[ \t]*[-*+][ \t]+/m',
        '/^[ \t]*\d{1,3}[.)][ \t]+/m',
        '/(?<![\w*])\*(?=\S)[^*\n]*\S\*(?![\w*])/',
    ];

    /**
     * Konversi untuk pertanyaan dan pembahasan: hasilnya boleh berupa blok.
     */
    public function konversiBlok(?string $isi): ?string
    {
        if (! $this->layak($isi)) {
            return $isi;
        }

        $simpanan = [];
        $teks = $this->lindungiKode($isi, $simpanan, true);
        $teks = $this->susunBlok($teks);
        $teks = $this->sapuInline($teks);

        return $this->pulihkan($teks, $simpanan);
    }

    /**
     * Konversi untuk opsi jawaban dan pernyataan: hasilnya tetap konten frasa.
     */
    public function konversiInline(?string $isi): ?string
    {
        if (! $this->layak($isi)) {
            return $isi;
        }

        $simpanan = [];
        $teks = $this->lindungiKode($isi, $simpanan, false);
        $teks = $this->sapuInline($teks);
        $teks = preg_replace('/\R/', '<br>', $teks);

        return $this->pulihkan($teks, $simpanan);
    }

    private function layak(?string $isi): bool
    {
        if ($isi === null || $isi === '' || preg_match(self::TAG_DIKENAL, $isi) === 1) {
            return false;
        }

        foreach (self::TANDA_MARKDOWN as $pola) {
            if (preg_match($pola, $isi) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menaruh isi kode ke dalam simpanan lalu menggantinya dengan penanda, supaya
     * aturan berikutnya tidak menyentuhnya.
     *
     * @param  list<string>  $simpanan
     */
    private function lindungiKode(string $isi, array &$simpanan, bool $blokBerkelompok): string
    {
        // Pasangan ``` yang ganjil: AI kadang memutus fence di tengah, dan sisa yang
        // menggantung begitu saja akan tampil sebagai tanda backtick tiga kali.
        if (substr_count($isi, '```') % 2 === 1) {
            $isi .= "\n```";
        }

        // Fence multi-baris. Baris pembuka boleh memuat penanda bahasa; bila isinya kosong
        // maka baris pembuka itulah isi kode yang ditulis tanpa baris baru.
        $isi = preg_replace_callback(
            '/```[ \t]*([^\n`]*)\R(.*?)```/s',
            function (array $cocok) use (&$simpanan, $blokBerkelompok): string {
                $isi = trim($cocok[2], "\r\n");

                if ($isi === '') {
                    return $this->simpan('<code>'.$this->aman(trim($cocok[1])).'</code>', $simpanan);
                }

                $tanda = $this->simpan('<pre><code>'.$this->aman($isi).'</code></pre>', $simpanan);

                // Baris kosong di kedua sisi menjaga blok kode berdiri sendiri, sehingga
                // penyusun blok tidak pernah menaruhnya di dalam <p>.
                return $blokBerkelompok ? "\n\n{$tanda}\n\n" : $tanda;
            },
            $isi,
        );

        // Fence satu baris, mis. ```console.log(x)``` tanpa baris baru.
        $isi = preg_replace_callback(
            '/```([^`\r\n]*)```/',
            function (array $cocok) use (&$simpanan): string {
                return $this->simpan('<code>'.$this->aman($cocok[1]).'</code>', $simpanan);
            },
            $isi,
        );

        // Kode inline satu backtick, diproses paling akhir supaya tidak pernah
        // menelan backtick milik fence yang sudah dipasangi penanda sebelumnya.
        return preg_replace_callback(
            '/`([^`\n]+)`/',
            function (array $cocok) use (&$simpanan): string {
                return $this->simpan('<code>'.$this->aman($cocok[1]).'</code>', $simpanan);
            },
            $isi,
        );
    }

    /**
     * Menaruh satu potongan HTML ke simpanan lalu mengembalikan penandanya.
     *
     * @param  list<string>  $simpanan
     */
    private function simpan(string $html, array &$simpanan): string
    {
        $simpanan[] = $html;

        return "\x1A".(count($simpanan) - 1)."\x1A";
    }

    /**
     * Menyusun teks baris demi baris menjadi judul, daftar, blok kode, dan paragraf.
     */
    private function susunBlok(string $teks): string
    {
        $keluaran = [];
        $paragraf = [];
        $daftar = null;

        $tutupParagraf = function () use (&$keluaran, &$paragraf): void {
            if ($paragraf === []) {
                return;
            }

            $keluaran[] = '<p>'.preg_replace('/\R/', '<br>', implode("\n", $paragraf)).'</p>';
            $paragraf = [];
        };

        $tutupDaftar = function () use (&$keluaran, &$daftar): void {
            if ($daftar === null) {
                return;
            }

            $butir = implode('', array_map(
                fn (string $isi): string => '<li>'.$isi.'</li>',
                $daftar['isi'],
            ));
            $keluaran[] = '<'.$daftar['tipe'].'>'.$butir.'</'.$daftar['tipe'].'>';
            $daftar = null;
        };

        foreach (preg_split('/\R/', $teks) as $baris) {
            if (trim($baris) === '') {
                $tutupParagraf();
                $tutupDaftar();

                continue;
            }

            if (preg_match('/^\x1A\d+\x1A$/', trim($baris)) === 1) {
                $tutupParagraf();
                $tutupDaftar();
                $keluaran[] = trim($baris);

                continue;
            }

            if (preg_match('/^(#{1,6})[ \t]+(.+?)\s*$/', $baris, $cocok) === 1) {
                $tutupParagraf();
                $tutupDaftar();

                // Sanitizer hanya mengizinkan h1 sampai h3.
                $tingkat = min(strlen($cocok[1]), 3);
                $keluaran[] = '<h'.$tingkat.'>'.$cocok[2].'</h'.$tingkat.'>';

                continue;
            }

            $penanda = $this->penandaDaftar($baris);

            if ($penanda !== null) {
                $tutupParagraf();

                if ($daftar !== null && $daftar['tipe'] !== $penanda['tipe']) {
                    $tutupDaftar();
                }

                $daftar ??= ['tipe' => $penanda['tipe'], 'isi' => []];
                $daftar['isi'][] = $penanda['isi'];

                continue;
            }

            $tutupDaftar();
            $paragraf[] = $baris;
        }

        $tutupParagraf();
        $tutupDaftar();

        return implode("\n", $keluaran);
    }

    /**
     * @return array{tipe: string, isi: string}|null
     */
    private function penandaDaftar(string $baris): ?array
    {
        if (preg_match('/^[ \t]*[-*+][ \t]+(.+?)\s*$/', $baris, $cocok) === 1) {
            return ['tipe' => 'ul', 'isi' => $cocok[1]];
        }

        if (preg_match('/^[ \t]*\d{1,3}[.)][ \t]+(.+?)\s*$/', $baris, $cocok) === 1) {
            return ['tipe' => 'ol', 'isi' => $cocok[1]];
        }

        return null;
    }

    /**
     * Tebal lalu miring. Keduanya dibatasi tepi kata supaya `2*3*4` dalam soal
     * matematika tidak pernah tertukar dengan penanda miring.
     */
    private function sapuInline(string $teks): string
    {
        $teks = preg_replace('/(?<![\w*])\*\*(?=\S)([^*]+?\S)\*\*(?![\w*])/s', '<strong>$1</strong>', $teks);

        return preg_replace('/(?<![\w*])\*(?=\S)([^*\n]+?\S)\*(?![\w*])/s', '<em>$1</em>', $teks);
    }

    /**
     * @param  list<string>  $simpanan
     */
    private function pulihkan(string $teks, array $simpanan): string
    {
        return preg_replace_callback(
            '/\x1A(\d+)\x1A/',
            fn (array $cocok): string => $simpanan[(int) $cocok[1]] ?? $cocok[0],
            $teks,
        );
    }

    private function aman(string $isi): string
    {
        return htmlspecialchars($isi, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
