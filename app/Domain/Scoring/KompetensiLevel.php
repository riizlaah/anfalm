<?php

namespace App\Domain\Scoring;

class KompetensiLevel
{
    public const MAHIR = 'mahir';

    public const MENENGAH = 'menengah';

    public const DASAR = 'dasar';

    public const PERLU_BIMBINGAN = 'perlu_bimbingan';

    public const BELUM_TERIDENTIFIKASI = 'belum_teridentifikasi';

    private const LABELS = [
        self::MAHIR => 'Mahir',
        self::MENENGAH => 'Menengah',
        self::DASAR => 'Dasar',
        self::PERLU_BIMBINGAN => 'Perlu Bimbingan',
        self::BELUM_TERIDENTIFIKASI => 'Belum Teridentifikasi',
    ];

    /**
     * Nilai ordinal tiap level, dipakai sebagai sumbu-y grafik batang halaman
     * Analisis Kompetensi (3.9 butir 3) agar lima kategori bisa dibandingkan.
     *
     * @var array<string, int>
     */
    private const URUTAN = [
        self::BELUM_TERIDENTIFIKASI => 0,
        self::PERLU_BIMBINGAN => 1,
        self::DASAR => 2,
        self::MENENGAH => 3,
        self::MAHIR => 4,
    ];

    /**
     * Kalimat ajakan latihan per level, dipakai halaman Analisis Kompetensi (3.9).
     *
     * @var array<string, string>
     */
    private const REKOMENDASI = [
        self::MAHIR => 'Pertahankan, KD ini sudah Mahir. Tantang dirimu dengan soal lebih sulit.',
        self::MENENGAH => 'Lanjutkan latihan KD ini agar naik ke Mahir.',
        self::DASAR => 'Terus latihan KD ini agar naik ke Menengah.',
        self::PERLU_BIMBINGAN => 'Fokus latihan KD ini karena masih Perlu Bimbingan.',
        self::BELUM_TERIDENTIFIKASI => 'Belum ada data untuk KD ini. Kerjakan latihan agar kompetensimu teridentifikasi.',
    ];

    /**
     * Isi kartu latihan per mapel di dashboard (butir C2): ajakan yang
     * ditulis dan label tombolnya.
     *
     * Berbeda dengan `REKOMENDASI` yang bicara tentang satu KD, kalimat di
     * sini bicara tentang seluruh mapel dan berdiri sendiri di balik nama
     * mapel — karena itu kalimatnya tidak diambil dari `REKOMENDASI` begitu
     * saja: kalimat yang benar untuk satu kompetensi dasar ("KD ini") akan
     * salah ketika dibaca di bawah judul "Matematika".
     *
     * @var array<string, array{ajakan: string, tombol: string}>
     */
    private const KARTU_LATIHAN = [
        self::MAHIR => [
            'ajakan' => 'Sudah Mahir. Latihan rutin di mapel ini menjaganya tetap demikian.',
            'tombol' => 'Pertahankan',
        ],
        self::MENENGAH => [
            'ajakan' => 'Tinggal selangkah lagi sebelum Mahir.',
            'tombol' => 'Kejar Mahir',
        ],
        self::DASAR => [
            'ajakan' => 'Dasarnya sudah terbentuk, tinggal diperkuat.',
            'tombol' => 'Perkuat Dasar',
        ],
        self::PERLU_BIMBINGAN => [
            'ajakan' => 'Masih Perlu Bimbingan. Bangun fondasinya lewat latihan rutin.',
            'tombol' => 'Latih Sekarang',
        ],
        self::BELUM_TERIDENTIFIKASI => [
            'ajakan' => 'Belum ada data. Latihan pertamamu akan mengisi papan ini.',
            'tombol' => 'Mulai Latihan',
        ],
    ];

    /**
     * Klasifikasi level kompetensi sesuai 7.5. Data kosong dianggap belum teridentifikasi.
     */
    public function levelFor(?float $theta): string
    {
        if ($theta === null) {
            return self::BELUM_TERIDENTIFIKASI;
        }

        if ($theta >= 1.5) {
            return self::MAHIR;
        }

        if ($theta >= 0.5) {
            return self::MENENGAH;
        }

        if ($theta >= -0.5) {
            return self::DASAR;
        }

        if ($theta >= -1.5) {
            return self::PERLU_BIMBINGAN;
        }

        return self::BELUM_TERIDENTIFIKASI;
    }

    public function label(string $level): string
    {
        return self::LABELS[$level] ?? $level;
    }

    /**
     * Posisi level pada skala 0–4. Level yang tak dikenal dianggap belum
     * teridentifikasi.
     */
    public function urut(string $level): int
    {
        return self::URUTAN[$level] ?? self::URUTAN[self::BELUM_TERIDENTIFIKASI];
    }

    public function rekomendasi(string $level): string
    {
        return self::REKOMENDASI[$level] ?? $level;
    }

    /**
     * Isi kartu latihan di dashboard untuk satu level. Level yang tidak
     * dikenal mengikuti `BELUM_TERIDENTIFIKASI`, bukan mengembalikan
     * kuncinya sendiri — di sini kunci itu bocor ke layar sebagai kalimat
     * yang tidak bisa dibaca peserta.
     *
     * @return array{ajakan: string, tombol: string}
     */
    public function kartuLatihan(string $level): array
    {
        return self::KARTU_LATIHAN[$level]
            ?? self::KARTU_LATIHAN[self::BELUM_TERIDENTIFIKASI];
    }

    /**
     * Rekomendasi latihan yang dihasilkan dari theta, mengikuti rantai
     * theta → level → rekomendasi pada 3.9.
     */
    public function rekomendasiFor(?float $theta): string
    {
        return $this->rekomendasi($this->levelFor($theta));
    }
}
