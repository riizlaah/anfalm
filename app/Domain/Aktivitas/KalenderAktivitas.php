<?php

namespace App\Domain\Aktivitas;

use Carbon\CarbonInterface;

class KalenderAktivitas
{
    /**
     * Jendela kalender dalam minggu kalender penuh, tiap minggu Senin–Minggu.
     */
    public const MINGGU_TAMPIL = 8;

    /**
     * Susun kalender aktivitas delapan minggu terakhir beserta streak beruntun.
     *
     * @param  array<string, int>  $jumlahPerTanggal  jumlah percobaan per tanggal (Y-m-d). Sengaja tidak dibatasi jendela
     *                                                kalendernya: streak bisa lebih tua dari delapan minggu yang ditampilkan.
     * @param  CarbonInterface  $hariIni  hari yang sedang dilihat, dilewatkan eksplisit supaya perhitungannya bisa diuji
     * @return array{
     *     streak: int,
     *     hari_aktif: int,
     *     minggu: list<list<array{tanggal: string, jumlah: int, tingkat: int}>>
     * }
     */
    public function susun(array $jumlahPerTanggal, CarbonInterface $hariIni): array
    {
        // Awal minggu berjalan dihitung sendiri lewat format ISO-8601 `N`
        // (1 = Senin … 7 = Minggu), bukan lewat `startOfWeek()` Carbon yang
        // ikut aturan minggu locale. Dihitung mundur dari Senin supaya kolom
        // pertama selalu Senin dan kolom terakhir selalu Minggu, berapa pun
        // hari yang sudah terlewat pada minggu ini.
        $seninMingguIni = $hariIni->copy()->subDays((int) $hariIni->format('N') - 1)->startOfDay();
        $mingguPertama = $seninMingguIni->subWeeks(self::MINGGU_TAMPIL - 1);

        $minggu = [];
        $hariAktif = 0;

        for ($mingguKe = 0; $mingguKe < self::MINGGU_TAMPIL; $mingguKe++) {
            $hari = $mingguPertama->copy()->addWeeks($mingguKe);
            $sel = [];

            for ($hariKe = 0; $hariKe < 7; $hariKe++) {
                $tanggal = $hari->toDateString();
                $jumlah = $jumlahPerTanggal[$tanggal] ?? 0;
                $hariAktif += $jumlah > 0 ? 1 : 0;

                $sel[] = [
                    'tanggal' => $tanggal,
                    'jumlah' => $jumlah,
                    'tingkat' => $this->tingkat($jumlah),
                ];

                $hari = $hari->addDay();
            }

            $minggu[] = $sel;
        }

        return [
            'streak' => $this->streakBeruntun($jumlahPerTanggal, $hariIni),
            'hari_aktif' => $hariAktif,
            'minggu' => $minggu,
        ];
    }

    /**
     * Jumlah hari beruntun yang paling baru, dihitung mundur dari hari ini.
     *
     * @param  array<string, int>  $jumlahPerTanggal
     */
    private function streakBeruntun(array $jumlahPerTanggal, CarbonInterface $hariIni): int
    {
        // Streak hari ini belum tentu sudah terisi — selama kemarin terisi dia
        // masih hidup, jadi penghitungan dimulai dari hari pertama yang mungkin
        // berisi. Bila hari itu pun kosong, loop tidak jalan dan hasilnya nol.
        $hari = isset($jumlahPerTanggal[$hariIni->toDateString()])
            ? $hariIni->copy()
            : $hariIni->copy()->subDay();

        $streak = 0;

        while (isset($jumlahPerTanggal[$hari->toDateString()])) {
            $streak++;
            $hari = $hari->subDay();
        }

        return $streak;
    }

    /**
     * Kepadatan sel, mengikuti jumlah percobaan pada hari itu.
     *
     * Satu hingga dua percobaan terasa masih ringan, tiga sampai empat mulai
     * padat, lima ke atas berarti hari itu penuh latihan.
     */
    private function tingkat(int $jumlah): int
    {
        return match (true) {
            $jumlah === 0 => 0,
            $jumlah <= 2 => 1,
            $jumlah <= 4 => 2,
            default => 3,
        };
    }
}
