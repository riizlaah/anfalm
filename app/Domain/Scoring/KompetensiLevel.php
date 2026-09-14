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
}
