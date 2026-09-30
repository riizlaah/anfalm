<?php

namespace App\Domain\Percobaan;

use App\Domain\Scoring\IrtService;
use App\Domain\Scoring\ScoringService;
use App\Models\HasilTryout;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siklus hidup percobaan peserta: mulai → kerjakan → tutup.
 *
 * Aturan yang dipegang di sini supaya tidak terpecah ke controller:
 * - soal diacak sekali saat mulai, lalu hasilnya disimpan (6.4);
 * - satu percobaan per peserta per paket tryout (6.16);
 * - jawaban hanya bisa masuk untuk mapel yang sedang dikerjakan;
 * - penutupan menghitung theta per mapel lalu merata-ratkannya (7.4).
 */
class PercobaanService
{
    /**
     * Di bawah jumlah item ini estimasi memakai prior lemah, karena MLE pada
     * data sangat sedikit mudah meledak ke ±3.
     */
    private const MIN_ITEM_MLE = 3;

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly IrtService $irt,
    ) {}

    /**
     * Menyusun daftar soal per mapel untuk satu percobaan. Urutan diacak di
     * sini saja lalu disimpan ke `percobaan.daftar_soal`, supaya tidak diacak
     * ulang pada tiap request dan peserta tidak melihat urutan berubah.
     *
     * @return array<int, array{mapel_id: int, soal_ids: array<int, int>}>
     */
    public function susunDaftarSoal(PaketTryout $paket): array
    {
        $grup = [];

        foreach (PaketTryout::SLOT as $slot) {
            $mapelId = (int) $paket->getAttribute($slot['mapel']);
            $paketSoal = PaketSoal::find((int) $paket->getAttribute($slot['paket']));

            if ($mapelId === 0 || $paketSoal === null) {
                continue;
            }

            $soalIds = $paketSoal->soal()->get()->pluck('id')->all();

            if ($soalIds === []) {
                continue;
            }

            shuffle($soalIds);

            $grup[] = ['mapel_id' => $mapelId, 'soal_ids' => $soalIds];
        }

        return $grup;
    }

    /**
     * Memulai percobaan tryout milik peserta, atau mengembalikan percobaan yang
     * masih berjalan bila sudah ada (6.4).
     *
     * Mengembalikan `null` bila paket itu sudah pernah menghasilkan nilai,
     * karena tiap peserta hanya boleh satu percobaan per paket (6.16).
     *
     * @throws RuntimeException bila paket tryout tidak berisi soal apa pun
     */
    public function mulai(PaketTryout $paket, User $user): ?Percobaan
    {
        return DB::transaction(function () use ($paket, $user): ?Percobaan {
            // percobaan tidak punya unique index pada (user, paket), jadi
            // kuncinya di sini agar klik ganda tidak membuat dua baris berjalan.
            $sudahDinilai = HasilTryout::query()
                ->where('user_id', $user->getKey())
                ->where('paket_tryout_id', $paket->getKey())
                ->lockForUpdate()
                ->first();

            if ($sudahDinilai !== null) {
                return null;
            }

            $berjalan = $this->cariAktif($paket, $user, true);

            if ($berjalan !== null) {
                return $berjalan;
            }

            $daftarSoal = $this->susunDaftarSoal($paket);

            if ($daftarSoal === []) {
                throw new RuntimeException("Paket tryout \"{$paket->nama_paket}\" tidak berisi soal apa pun.");
            }

            return Percobaan::create([
                'user_id' => $user->getKey(),
                'jenis' => Percobaan::JENIS_TRYOUT,
                'paket_tryout_id' => $paket->getKey(),
                'jumlah_soal' => array_sum(array_map(fn (array $grup): int => count($grup['soal_ids']), $daftarSoal)),
                'batas_waktu_menit' => $paket->batas_waktu_menit,
                'status' => Percobaan::STATUS_BERJALAN,
                'daftar_soal' => $daftarSoal,
                'urutan_mapel' => 0,
                'posisi_soal' => 0,
                'waktu_mulai' => now(),
            ]);
        });
    }

    /**
     * Percobaan milik peserta untuk paket ini yang masih berjalan, bila ada.
     */
    public function cariAktif(PaketTryout $paket, User $user, bool $kunci = false): ?Percobaan
    {
        $query = Percobaan::query()
            ->where('user_id', $user->getKey())
            ->where('paket_tryout_id', $paket->getKey())
            ->where('status', Percobaan::STATUS_BERJALAN);

        if ($kunci) {
            $query->lockForUpdate();
        }

        return $query->latest('id')->first();
    }

    /**
     * Kelompok soal yang sedang dikerjakan, atau null bila tidak ada.
     *
     * @return array{mapel_id: int, soal_ids: array<int, int>}|null
     */
    public function grupAktif(Percobaan $percobaan): ?array
    {
        return ($percobaan->daftar_soal ?? [])[(int) $percobaan->urutan_mapel] ?? null;
    }

    /**
     * Menyimpan jawaban untuk mapel yang sedang dikerjakan saja. Soal di luar
     * mapel aktif tidak pernah disentuh, jadi jawaban tidak bisa bocor ke
     * mapel berikutnya yang masih terkunci. Soal yang dikosongkan kembali
     * dihapus agar tidak dihitung sebagai terjawab.
     *
     * @param  array<string, mixed>  $jawaban
     */
    public function simpanJawaban(Percobaan $percobaan, array $jawaban): void
    {
        if (! $percobaan->isBerjalan()) {
            return;
        }

        $grup = $this->grupAktif($percobaan);

        if ($grup === null) {
            return;
        }

        $soals = Soal::with(['opsiJawaban', 'pernyataanKategori'])
            ->whereIn('id', $grup['soal_ids'])
            ->get()
            ->keyBy('id');

        foreach ($grup['soal_ids'] as $soalId) {
            $soal = $soals->get((int) $soalId);

            if ($soal === null) {
                continue;
            }

            $terkirim = $this->normalisasiJawaban($soal, $jawaban);

            if ($terkirim === null) {
                RiwayatPengerjaan::query()
                    ->where('percobaan_id', $percobaan->getKey())
                    ->where('soal_id', $soal->getKey())
                    ->delete();

                continue;
            }

            RiwayatPengerjaan::updateOrCreate(
                [
                    'percobaan_id' => $percobaan->getKey(),
                    'soal_id' => $soal->getKey(),
                ],
                [
                    'user_id' => $percobaan->user_id,
                    'paket_tryout_id' => $percobaan->paket_tryout_id,
                    'jawaban_user' => $terkirim,
                    'is_benar' => false,
                    'mode' => $percobaan->jenis,
                    'waktu_mulai' => $percobaan->waktu_mulai,
                    'waktu_selesai' => null,
                ]
            );
        }
    }

    /**
     * Maju ke mapel berikutnya.
     *
     * Mengembalikan `false` bila mapel yang baru saja dikerjakan adalah yang
     * terakhir, supaya pemanggil tahu bahwa percobaan harus ditutup.
     */
    public function lanjut(Percobaan $percobaan): bool
    {
        if (! $percobaan->isBerjalan()) {
            return false;
        }

        $posisi = (int) $percobaan->urutan_mapel;

        if ($posisi + 1 >= count($percobaan->daftar_soal ?? [])) {
            return false;
        }

        $percobaan->update(['urutan_mapel' => $posisi + 1]);

        return true;
    }

    /**
     * Menutup percobaan: menilai tiap jawaban, mengestimasi theta per mapel,
     * merata-ratanya (mapel yang tidak dijawab ditinggalkan, 7.4), lalu
     * menyimpan ke `hasil_tryout`. Panggil berulang pun hasilnya tetap sama.
     */
    public function akhirkan(Percobaan $percobaan): HasilTryout
    {
        return DB::transaction(function () use ($percobaan): HasilTryout {
            $percobaan = Percobaan::query()
                ->whereKey($percobaan->getKey())
                ->lockForUpdate()
                ->first() ?? $percobaan;

            $sudahAda = HasilTryout::query()
                ->where('user_id', $percobaan->user_id)
                ->where('paket_tryout_id', $percobaan->paket_tryout_id)
                ->first();

            if ($sudahAda !== null) {
                return $sudahAda;
            }

            $percobaan->loadMissing('user');
            $selesaiPada = now();
            $indeksMapel = $this->indeksMapelPerSoal($percobaan);

            $riwayat = RiwayatPengerjaan::query()
                ->with('soal')
                ->where('percobaan_id', $percobaan->getKey())
                ->get();

            $itemsPerMapel = [];
            $itemTerjawab = [];
            $jumlahBenar = 0;
            $jumlahSalah = 0;
            $skorIrtTotal = 0.0;

            foreach ($riwayat as $baris) {
                if ($baris->soal === null) {
                    continue;
                }

                $skor = $this->scoring->score($baris->soal, $baris->jawaban_user);
                $dijawab = ($skor['jumlah_benar'] + $skor['jumlah_salah']) > 0;
                $benar = $dijawab && $skor['jumlah_salah'] === 0;
                $proporsi = $dijawab ? $skor['jumlah_benar'] / $skor['total_soal'] : null;

                if ($dijawab) {
                    $benar ? $jumlahBenar++ : $jumlahSalah++;
                    $skorIrtTotal += (float) $proporsi;
                }

                $baris->is_benar = $benar;
                $baris->skor_irt = $proporsi;
                $baris->waktu_selesai = $selesaiPada;
                $baris->save();

                $indeks = $indeksMapel[(int) $baris->soal_id] ?? null;
                $items = array_values(array_filter(
                    $skor['items'],
                    fn (array $item): bool => $item['resp'] !== null
                ));

                if ($indeks === null || $items === []) {
                    continue;
                }

                $itemsIrt = $this->keItemIrt($items);

                $itemsPerMapel[$indeks] = [...($itemsPerMapel[$indeks] ?? []), ...$itemsIrt];
                $itemTerjawab = [...$itemTerjawab, ...$itemsIrt];
            }

            $thetaPerMapel = $this->thetaTiapMapel($itemsPerMapel);
            $thetaFinal = $thetaPerMapel === []
                ? 0.0
                : array_sum($thetaPerMapel) / count($thetaPerMapel);

            // Theta per baris baru bisa ditulis setelah ketahuan, supaya semua
            // soal dalam satu mapel memakai theta mapel yang sama.
            foreach ($riwayat as $baris) {
                $indeks = $indeksMapel[(int) $baris->soal_id] ?? null;
                $theta = $indeks !== null ? ($thetaPerMapel[$indeks] ?? null) : null;

                if ($theta !== null) {
                    $baris->forceFill(['theta_est_moment' => $theta])->save();
                }
            }

            $durasi = $this->hitungDurasi($percobaan, $selesaiPada);

            $percobaan->update([
                'status' => Percobaan::STATUS_SELESAI,
                'waktu_selesai' => $selesaiPada,
                'durasi_detik' => $durasi,
            ]);

            return HasilTryout::create([
                'user_id' => $percobaan->user_id,
                'paket_tryout_id' => $percobaan->paket_tryout_id,
                'theta_final' => $thetaFinal,
                'standard_error' => $this->irt->standardError($thetaFinal, $itemTerjawab),
                'skor_irt_total' => round($skorIrtTotal, 3),
                'skor_konversi' => $this->irt->convertToScale($thetaFinal, $percobaan->user->tingkat),
                'jumlah_benar' => $jumlahBenar,
                'jumlah_salah' => $jumlahSalah,
                'total_soal' => $jumlahBenar + $jumlahSalah,
                'durasi_total' => $durasi,
                'selesai_pada' => $selesaiPada,
            ]);
        });
    }

    /**
     * Menyesuaikan nama kunci item keluaran `ScoringService` dengan yang
     * dituntut `IrtService` (`resp` → `response`).
     *
     * Item yang `resp`-nya null (pernyataan kategori yang tidak dijawab) harus
     * disaring lebih dulu, karena estimasi theta hanya menerima jawaban nyata.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{a: float, b: float, c: float, response: int}>
     */
    private function keItemIrt(array $items): array
    {
        return array_map(fn (array $item): array => [
            'a' => (float) ($item['a'] ?? IrtService::DEFAULT_A),
            'b' => (float) ($item['b'] ?? IrtService::DEFAULT_B),
            'c' => (float) ($item['c'] ?? IrtService::DEFAULT_C),
            'response' => (int) $item['resp'],
        ], $items);
    }

    /**
     * Pemetaan soal ke posisi kelompok mapelnya di daftar percobaan ini.
     *
     * @return array<int, int>
     */
    private function indeksMapelPerSoal(Percobaan $percobaan): array
    {
        $indeks = [];

        foreach (($percobaan->daftar_soal ?? []) as $posisi => $grup) {
            foreach ($grup['soal_ids'] as $soalId) {
                $indeks[(int) $soalId] = $posisi;
            }
        }

        return $indeks;
    }

    /**
     * Theta tiap mapel dari item yang punya jawaban. Mapel tanpa satu pun
     * jawaban tidak muncul di sini sehingga ikut terbuang dari rata-rata (7.4).
     *
     * @param  array<int, array<int, array{a: float, b: float, c: float, response: int}>>  $itemsPerMapel
     * @return array<int, float>
     */
    private function thetaTiapMapel(array $itemsPerMapel): array
    {
        $theta = [];

        foreach ($itemsPerMapel as $posisi => $items) {
            $theta[$posisi] = count($items) < self::MIN_ITEM_MLE
                ? $this->irt->estimateWithPrior($items)
                : $this->irt->estimateMle($items);
        }

        return $theta;
    }

    /**
     * Durasi pengerjaan dalam detik, tidak pernah melewati batas waktu paket
     * agar peserta yang terlambat mengumpulkan tidak keunggulan di leaderboard.
     */
    private function hitungDurasi(Percobaan $percobaan, DateTimeInterface $selesaiPada): int
    {
        $mulai = $percobaan->waktu_mulai ?? $selesaiPada;
        $durasi = max(0, (int) round($mulai->diffInSeconds($selesaiPada)));

        if ($percobaan->batas_waktu_menit !== null) {
            $durasi = min($durasi, (int) $percobaan->batas_waktu_menit * 60);
        }

        return $durasi;
    }

    /**
     * Menyaring jawaban mentah dari form agar sesuai tipe soal dan hanya
     * menunjuk opsi/pernyataan milik soal itu sendiri.
     *
     * @param  array<string, mixed>  $jawaban
     * @return array<string, mixed>|null null bila soal ini tidak dijawab
     */
    private function normalisasiJawaban(Soal $soal, array $jawaban): ?array
    {
        if ($soal->tipe_soal === Soal::TIPE_PG_KATEGORI) {
            return $this->normalisasiKategori($soal, $jawaban);
        }

        $dikirim = $jawaban['opsi'][$soal->id] ?? null;

        if ($dikirim === null) {
            return null;
        }

        if (is_array($dikirim)) {
            $dikirim = reset($dikirim);
        }

        if (! is_numeric($dikirim)) {
            return null;
        }

        $opsiSah = $soal->opsiJawaban->pluck('id')->flip();

        if ($soal->tipe_soal === Soal::TIPE_PG) {
            $dipilih = (int) $dikirim;

            return $opsiSah->has($dipilih) ? ['opsi' => $dipilih] : null;
        }

        $dipilih = array_values(array_filter(
            array_map('intval', (array) ($jawaban['opsi'][$soal->id] ?? [])),
            fn (int $id): bool => $opsiSah->has($id)
        ));

        return $dipilih === [] ? null : ['opsi' => $dipilih];
    }

    /**
     * @param  array<string, mixed>  $jawaban
     * @return array<string, mixed>|null
     */
    private function normalisasiKategori(Soal $soal, array $jawaban): ?array
    {
        $dikirim = (array) ($jawaban['kategori'][$soal->id] ?? []);
        $pernyataanSah = $soal->pernyataanKategori->pluck('id')->flip();
        $kategori = [];

        foreach ($dikirim as $pernyataanId => $nilai) {
            if ($pernyataanSah->has((int) $pernyataanId) && is_string($nilai) && $nilai !== '') {
                $kategori[(int) $pernyataanId] = $nilai;
            }
        }

        return $kategori === [] ? null : ['kategori' => $kategori];
    }
}
