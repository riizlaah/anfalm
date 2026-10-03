<?php

namespace App\Domain\Scoring;

use App\Models\KompetensiDasar;
use App\Models\RiwayatPengerjaan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Rekonstruksi perkembangan kompetensi dari riwayat jawaban per soal.
 *
 * `tracking_kompetensi` hanya menyimpan theta terkini — satu baris per KD,
 * ditulis ulang tiap kali percobaan ditutup — jadi tidak ada deret waktu yang
 * bisa digambar, dan menambah kolomnya hanya demi satu grafik tidak sebanding
 * dengan migrasi serta penulisan ganda yang dibawanya.
 *
 * Riwayat per soalnya sendiri lengkap: `riwayat_pengerjaan` mencatat tiap
 * jawaban beserta waktunya. Dengan memotong riwayat itu pada batas tanggal
 * lalu menghitung ulang theta melalui fungsi dan himpunan item yang sama
 * persis dengan `PercobaanService::perbaruiTrackingKompetensi()`, garis
 * perkembangannya dapat dipulihkan tanpa skema baru — dan titik terakhirnya
 * dijamin identik dengan `theta_estimasi` yang kini tersimpan.
 */
class JejakKompetensi
{
    /**
     * Jumlah tanggal yang digambar, dihitung dari yang terbaru.
     *
     * Memotong dari sisi awal membatasi kerja rekonstruksi: setiap KD paling
     * banyak dihitung ulang sebanyak ini kali, bukan sebanyak umur akun.
     * Nilai pada tanggal pertama jendela tetap dibangun dari *seluruh* jawaban
     * sebelumnya, jadi riwayatnya tidak dipotong — hanya titik-titik awalnya
     * yang tidak digambar. Sumbu tanggalnya pun tetap terbaca di 360px.
     */
    public const BATAS_TANGGAL = 20;

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly IrtService $irt,
    ) {}

    /**
     * Garis perkembangan skor IRT tiap KD pada satu mapel, per tanggal.
     *
     * Hanya KD yang punya jawaban yang muncul: KD tanpa riwayat berarti garis
     * yang seluruh titiknya kosong, dan legenda berisi baris tak terbaca hanya
     * menutupi ruang kanvas.
     *
     * @param  array<int, int>  $kdIds
     * @return array{labels: array<int, string>, seri: array<int, array{kode: string, nilai: array<int, int|null>}>, batas: array{0: int, 1: int}}
     */
    public function garisPerKd(User $user, array $kdIds): array
    {
        $batas = $this->irt->rentangSkor($user->tingkat);
        $perTanggal = $this->kelompokPerTanggal($user, $kdIds);

        if ($perTanggal === []) {
            return ['labels' => [], 'seri' => [], 'batas' => $batas];
        }

        $semua = array_keys($perTanggal);
        $jendela = array_slice($semua, -self::BATAS_TANGGAL);

        $labels = array_map(
            fn (string $tanggal): string => Carbon::parse($tanggal)->format('d/m/Y'),
            $jendela,
        );

        $kds = KompetensiDasar::query()
            ->whereIn('id', $kdIds)
            ->get(['id', 'kode_kompetensi']);

        $seri = [];

        foreach ($kds as $kd) {
            $nilai = $this->deretUntuk(
                $perTanggal,
                (int) $kd->getKey(),
                array_diff($semua, $jendela),
                $jendela,
                $user->tingkat,
            );

            // KD yang tak punya satu pun jawaban sama sekali tidak digambar —
            // serinya akan kosong sepanjang sumbu dan hanya memenuhi legenda.
            if (array_filter($nilai, fn (?int $n): bool => $n !== null) === []) {
                continue;
            }

            $seri[] = ['kode' => $kd->kode_kompetensi, 'nilai' => $nilai];
        }

        return ['labels' => $labels, 'seri' => $seri, 'batas' => $batas];
    }

    /**
     * Item IRT per tanggal terurut waktu, memuat seluruh KD peta kompetensi.
     *
     * @param  array<int, int>  $kdIds
     * @return array<string, array<int, array<int, array{a: float, b: float, c: float, response: int}>>>
     */
    private function kelompokPerTanggal(User $user, array $kdIds): array
    {
        $riwayat = RiwayatPengerjaan::query()
            ->with(['soal.opsiJawaban', 'soal.pernyataanKategori'])
            ->where('user_id', $user->getKey())
            ->whereHas('soal', fn ($q) => $q->whereIn('kompetensi_dasar_id', $kdIds))
            ->orderByRaw('COALESCE(waktu_selesai, created_at) ASC, id ASC')
            ->get();

        $perTanggal = [];

        foreach ($riwayat as $baris) {
            $soal = $baris->soal;

            if ($soal === null || $soal->kompetensi_dasar_id === null) {
                continue;
            }

            $skor = $this->scoring->score($soal, $baris->jawaban_user);

            // Baris yang belum terjawab sama sekali bukan respons, jadi tidak
            // punya tempat di model — aturan yang sama dipakai tracking.
            if (($skor['jumlah_benar'] + $skor['jumlah_salah']) === 0) {
                continue;
            }

            $items = $this->scoring->itemsIrt(array_values(array_filter(
                $skor['items'],
                fn (array $item): bool => $item['resp'] !== null,
            )));

            if ($items === []) {
                continue;
            }

            $tanggal = ($baris->waktu_selesai ?? $baris->created_at)->format('Y-m-d');
            $kdId = (int) $soal->kompetensi_dasar_id;

            $perTanggal[$tanggal][$kdId] = [
                ...($perTanggal[$tanggal][$kdId] ?? []),
                ...$items,
            ];
        }

        ksort($perTanggal);

        return $perTanggal;
    }

    /**
     * Satu baris seri garis: nilai pada tiap tanggal dalam `$dalamJendela`.
     *
     * @param  array<string, array<int, array<int, array{a: float, b: float, c: float, response: int}>>>  $perTanggal
     * @param  array<int, string>  $luarJendela
     * @param  array<int, string>  $dalamJendela
     * @return array<int, int|null>
     */
    private function deretUntuk(array $perTanggal, int $kdId, array $luarJendela, array $dalamJendela, ?string $tingkat): array
    {
        // Tanggal di luar jendela tetap ikut diakumulasikan; tanpa itu theta
        // pada titik pertama akan dihitung dari setengah riwayat.
        $akumulasi = [];

        foreach ($luarJendela as $tanggal) {
            $akumulasi = [...$akumulasi, ...($perTanggal[$tanggal][$kdId] ?? [])];
        }

        $theta = $this->estimate($akumulasi);
        $nilai = [];

        foreach ($dalamJendela as $tanggal) {
            if (isset($perTanggal[$tanggal][$kdId])) {
                $akumulasi = [...$akumulasi, ...$perTanggal[$tanggal][$kdId]];
                $theta = $this->estimate($akumulasi);
            }

            $nilai[] = $theta === null
                ? null
                : $this->irt->convertToScale($theta, $tingkat);
        }

        return $nilai;
    }

    /**
     * Estimasi yang meniru `perbaruiTrackingKompetensi()`: prior lemah di
     * bawah ambang item, MLE di atasnya.
     *
     * @param  array<int, array{a: float, b: float, c: float, response: int}>  $items
     */
    private function estimate(array $items): ?float
    {
        if ($items === []) {
            return null;
        }

        return count($items) < IrtService::MIN_ITEM_MLE
            ? $this->irt->estimateWithPrior($items)
            : $this->irt->estimateMle($items);
    }
}
