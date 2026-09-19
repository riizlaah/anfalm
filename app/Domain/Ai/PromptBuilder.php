<?php

namespace App\Domain\Ai;

use InvalidArgumentException;

class PromptBuilder
{
    public const TINGKAT_MUDAH = 'mudah';

    public const TINGKAT_SEDANG = 'sedang';

    public const TINGKAT_SULIT = 'sulit';

    public const TINGKAT_CAMPURAN = 'campuran';

    /**
     * @param  list<array{kode: string, deskripsi: string, materi_pokok?: string|null}>  $kompetensiDasars
     */
    public function build(
        array $kompetensiDasars,
        string $mapelNama,
        int $jumlahSoal,
        string $tingkatKesulitan,
        ?string $referensi = null,
    ): string {
        if ($jumlahSoal < 1) {
            throw new InvalidArgumentException('Jumlah soal minimal 1.');
        }

        if ($kompetensiDasars === []) {
            throw new InvalidArgumentException('Minimal satu kompetensi dasar wajib dipilih.');
        }

        $tingkat = in_array($tingkatKesulitan, $this->pilihanTingkat(), true)
            ? $tingkatKesulitan
            : self::TINGKAT_CAMPURAN;

        $distribusi = $this->distribusiMerata($jumlahSoal, count($kompetensiDasars));

        $kdLines = [];
        foreach ($kompetensiDasars as $i => $kd) {
            $materi = ! empty($kd['materi_pokok'])
                ? "\n      Materi pokok: {$kd['materi_pokok']}"
                : '';
            $kdLines[] = "      KD {$kd['kode']} - {$kd['deskripsi']}{$materi} (target: {$distribusi[$i]} soal)";
        }

        $referensiBlok = ($referensi !== null && trim($referensi) !== '')
            ? "\nReferensi tambahan (gunakan sebagai acuan materi):\n{$referensi}"
            : '';

        $kdList = implode("\n", $kdLines);

        return <<<PROMPT
        Anda adalah asisten AI yang bertugas membuat soal tryout TKA (Tes Kemampuan Akademik) untuk SMK/MAK.

        Tugas Anda:
        Buatkan {$jumlahSoal} soal untuk mapel {$mapelNama} dengan acuan Kompetensi Dasar (KD) berikut. Pembagian soal per KD harus mengikuti target di bawah ini.
        {$kdList}
        {$referensiBlok}

        Aturan pembuatan soal:
        1. Setiap soal harus memiliki:
           - tipe_soal: "pg" (Pilihan Ganda), "pg_kompleks" (lebih dari 1 jawaban benar), atau "pg_kategori" (pernyataan dikategorikan ke kategori kustom).
           - pertanyaan yang jelas dan tidak ambigu (bisa mengandung ekspresi matematika dalam format LaTeX, contoh: \( \frac{2}{3} \)).
           - opsi_jawaban: tepat 5 opsi dengan tepat 1 benar untuk "pg" dan minimal 2 benar untuk "pg_kompleks".
           - pernyataan_kategori: minimal 2 pernyataan (hanya untuk tipe "pg_kategori").
           - kompetensi_dasar yang sesuai dengan salah satu KD yang diberikan.
           - pembahasan yang edukatif dan mudah dipahami.

        2. Aturan khusus per tipe soal:
           a. PG (Pilihan Ganda):
              - Hanya 1 opsi dengan "is_benar": true; sisanya false.
           b. PG Kompleks:
              - Minimal 2 opsi dengan "is_benar": true (boleh semua benar).
              - Setiap opsi memiliki "parameter_irt" sendiri (a, b, c per opsi).
              - Field "parameter_irt" tingkat soal juga diperlukan sebagai ringkasan.
           c. PG Kategori:
              - Tidak menggunakan field "opsi_jawaban"; gunakan "pernyataan_kategori".
              - Setiap soal mendefinisikan "kategori_pg_kategori" (daftar kategori per-soal, contoh: ["Benar", "Salah"]) dan setiap pernyataan memakai salah satu kategori tersebut.
              - Minimal 2 pernyataan, maksimal 5 pernyataan.
              - Setiap pernyataan memiliki "parameter_irt" sendiri (a, b, c per pernyataan).

        3. Parameter IRT (a: daya beda, b: tingkat kesulitan, c: tebakan):
           - a: 0.5 sampai 2.5 (semakin tinggi semakin baik membedakan siswa pintar vs kurang pintar).
           - b: -3 sampai +3 (negatif = mudah, positif = sulit).
           - c: 0 sampai 0.35 (probabilitas tebakan).
           - Estimasi parameter mengikuti tingkat kesulitan: {$tingkat}.

        4. Distribusi tingkat kesulitan:
           - Jika tingkat kesulitan "campuran": 30% mudah (b < -0.5), 40% sedang (-0.5 <= b <= 0.5), 30% sulit (b > 0.5).
           - Jika "mudah": semua soal b < 0.
           - Jika "sedang": semua soal -1 <= b <= 1.
           - Jika "sulit": semua soal b > 0.

        5. Variasi soal:
           - Sebisa mungkin variasikan tipe soal (PG, PG Kompleks, PG Kategori) sesuai materi.
           - Setiap soal harus unik (tidak ada pertanyaan yang sama atau hampir sama).

        6. Output HARUS dalam satu format JSON yang valid dengan struktur berikut, tanpa teks lain di luar JSON:
        {
          "metadata": {
            "mapel": "{$mapelNama}",
            "jumlah_soal": {$jumlahSoal},
            "tingkat_kesulitan": "{$tingkat}"
          },
          "paket_soal": { "nama": "...", "deskripsi": "..." },
          "daftar_soal": [
            {
              "id_soal_sementara": "S001",
              "tipe_soal": "pg",
              "pertanyaan": "...",
              "gambar_url": null,
              "opsi_jawaban": [
                { "teks": "...", "is_benar": true, "urutan": 1, "parameter_irt": { "a_diskriminasi": 1.2, "b_kesulitan": -0.5, "c_tebakan": 0.2 } }
              ],
              "pembahasan": "...",
              "kompetensi_dasar": { "kode": "...", "deskripsi": "..." },
              "parameter_irt": { "a_diskriminasi": 1.2, "b_kesulitan": -0.5, "c_tebakan": 0.2 }
            }
          ]
        }
        JANGAN tambahkan teks di luar JSON. JANGAN gunakan markdown code block.
        PROMPT;
    }

    /**
     * @return list<int>
     */
    private function distribusiMerata(int $jumlahSoal, int $jumlahKd): array
    {
        $perKd = intdiv($jumlahSoal, $jumlahKd);
        $sisa = $jumlahSoal % $jumlahKd;

        return array_map(
            fn (int $index): int => $perKd + ($index < $sisa ? 1 : 0),
            range(0, $jumlahKd - 1),
        );
    }

    /**
     * @return list<string>
     */
    private function pilihanTingkat(): array
    {
        return [self::TINGKAT_MUDAH, self::TINGKAT_SEDANG, self::TINGKAT_SULIT, self::TINGKAT_CAMPURAN];
    }
}
