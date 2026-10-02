<?php

namespace App\Domain\Ai;

/**
 * Menjadwalkan bagian tiap Kompetensi Dasar (KD) dalam satu paket soal.
 *
 * Prompt AI memuat target soal per KD. Dulu target itu dihitung dari jumlah soal
 * satu part terhadap seluruh KD terpilih, sehingga KD di urutan akhir selalu
 * menerima `target: 0` — dan karena tiap part menghitung ulang dari nol,
 * part-part berikutnya menyasar KD awal yang sama pula. Akibatnya sebagian KD
 * tidak pernah diminta sama sekali.
 *
 * Penjadwal ini menghitung kuota dari **total paket**, lalu menyisihkannya part
 * demi part berdasarkan soal yang benar-benar sudah terkumpul, sehingga gabungan
 * seluruh part menutup distribusi global apa pun tingkat yield AI-nya.
 */
class PenjadwalKd
{
    /**
     * Kuota per KD untuk seluruh paket, dibagi merata dan sisa diberikan pada
     * KD terdepan sehingga jumlahnya persis sama dengan total.
     *
     * @param  list<string>  $kodeKd  urutan KD terpilih
     * @return list<array{kode: string, target: int}>
     */
    public function targetGlobal(int $totalSoal, array $kodeKd): array
    {
        if ($kodeKd === [] || $totalSoal < 1) {
            return [];
        }

        $perKd = intdiv($totalSoal, count($kodeKd));
        $sisa = $totalSoal % count($kodeKd);

        return array_map(
            fn (int $index, string $kode): array => [
                'kode' => $kode,
                'target' => $perKd + ($index < $sisa ? 1 : 0),
            ],
            array_keys($kodeKd),
            $kodeKd,
        );
    }

    /**
     * Kuota satu part: hanya KD yang masih punya sisa kuota, dibagi bergiliran
     * satu soal per giliran sampai jumlah part habis. KD ber-target nol tidak
     * pernah ikut, sehingga prompt tidak lagi menyuruh AI mengabaikan KD-nya
     * sendiri.
     *
     * `target` dihasilkan selalu berjumlah persis `$jumlahBagian` selama
     * `$jumlahBagian <= $totalSoal - jumlah soal terkumpul` — selisih kuota
     * global dengan yang terpakai tidak mungkin lebih kecil dari itu.
     *
     * @param  list<string>  $kodeKd  urutan KD terpilih, tetap antar part
     * @param  array<string, int>  $terpakai  kode KD => soal yang sudah terkumpul
     * @return list<array{kode: string, target: int}>
     */
    public function targetPart(int $totalSoal, array $kodeKd, array $terpakai, int $jumlahBagian): array
    {
        if ($kodeKd === [] || $jumlahBagian < 1) {
            return [];
        }

        $global = array_column($this->targetGlobal($totalSoal, $kodeKd), 'target');
        $hasil = array_fill_keys($kodeKd, 0);
        $sisa = $jumlahBagian;

        // Satu soal bergiliran: KD yang paling sedikit dipakai dilayani lebih
        // dulu, sehingga satu part menyebar ke banyak KD dan putusnya generate
        // di tengah jalan tidak mengubur setengah kuota pada satu KD.
        while ($sisa > 0) {
            $kandidat = [];

            foreach ($kodeKd as $index => $kode) {
                $kurangTersisa = $this->kurang($kode, $global[$index], $terpakai) - $hasil[$kode];

                if ($kurangTersisa > 0) {
                    $kandidat[] = [
                        'index' => $index,
                        'kode' => $kode,
                        'pakai' => (int) ($terpakai[$kode] ?? 0) + $hasil[$kode],
                        'kurang' => $kurangTersisa,
                    ];
                }
            }

            if ($kandidat === []) {
                break;
            }

            usort($kandidat, fn (array $a, array $b): int => $a['pakai'] <=> $b['pakai']
                ?: $b['kurang'] <=> $a['kurang']
                ?: $a['index'] <=> $b['index']);

            $hasil[$kandidat[0]['kode']]++;
            $sisa--;
        }

        // Urutan keluaran mengikuti urutan $kodeKd, bukan urutan penjadwalan,
        // supaya prompt selalu menyusun daftar KD dengan cara yang sama.
        return array_values(array_filter(
            array_map(
                fn (string $kode): array => ['kode' => $kode, 'target' => $hasil[$kode]],
                $kodeKd,
            ),
            fn (array $baris): bool => $baris['target'] > 0,
        ));
    }

    /**
     * @param  array<string, int>  $terpakai
     */
    private function kurang(string $kode, int $global, array $terpakai): int
    {
        return max(0, $global - (int) ($terpakai[$kode] ?? 0));
    }
}
