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
     * Rekomendasi latihan yang dihasilkan dari theta, mengikuti rantai
     * theta → level → rekomendasi pada 3.9.
     */
    public function rekomendasiFor(?float $theta): string
    {
        return $this->rekomendasi($this->levelFor($theta));
    }
}
