<?php

namespace App\Domain\Percobaan;

use App\Domain\Scoring\IrtService;
use App\Domain\Scoring\KompetensiLevel;
use App\Domain\Scoring\ScoringService;
use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use App\Models\TrackingKompetensi;
use App\Models\TrackingMapel;
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
        private readonly KompetensiLevel $level,
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
     * `$ulang` mengosongkan jawaban percobaan yang sedang berjalan dan
     * mengulang hitung mundur dari awal (6.4), tetapi tidak mengacak ulang
     * `daftar_soal` — urutan soal hanya ditentukan sekali per percobaan.
     *
     * Mengembalikan `null` bila paket itu sudah pernah menghasilkan nilai,
     * karena tiap peserta hanya boleh satu percobaan per paket (6.16).
     *
     * @throws RuntimeException bila paket tryout tidak berisi soal apa pun
     */
    public function mulai(PaketTryout $paket, User $user, bool $ulang = false): ?Percobaan
    {
        return DB::transaction(function () use ($paket, $user, $ulang): ?Percobaan {
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
                return $ulang ? $this->kosongkan($berjalan) : $berjalan;
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
     * Mengosongkan jawaban percobaan yang sedang berjalan lalu mengulang waktunya.
     *
     * `daftar_soal` sengaja dibiarkan: urutan soal hanya diacak sekali per
     * percobaan, bukan pada tiap percobaan ulang.
     */
    private function kosongkan(Percobaan $percobaan): Percobaan
    {
        $percobaan->riwayatPengerjaan()->delete();

        $percobaan->update([
            'status' => Percobaan::STATUS_BERJALAN,
            'urutan_mapel' => 0,
            'posisi_soal' => 0,
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_detik' => null,
        ]);

        return $percobaan;
    }

    /**
     * Mulai sesi latihan: soal diambil acak dari satu mapel, opsional hanya
     * dari satu kompetensi dasar, lalu dipotong sesuai jumlah yang diminta.
     *
     * `jumlah_soal` disimpan sebagai jumlah yang benar-benar terpakai, jadi
     * permintaan melebihi soal yang tersedia tetap sah.
     *
     * Mengembalikan `null` bila filter itu tidak menemukan soal apa pun.
     *
     * @param  array<string, mixed>  $pilihan  hasil validasi form latihan
     */
    public function mulaiLatihan(array $pilihan, User $user): ?Percobaan
    {
        $mapelId = (int) ($pilihan['mapel_id'] ?? 0);
        $jumlahSoal = (int) ($pilihan['jumlah_soal'] ?? 0);
        $kdId = isset($pilihan['kompetensi_dasar_id']) && $pilihan['kompetensi_dasar_id'] !== null
            ? (int) $pilihan['kompetensi_dasar_id']
            : null;

        $query = Soal::query()
            ->whereNotNull('kompetensi_dasar_id')
            ->whereHas('kompetensiDasar', fn ($q) => $q->where('mapel_id', $mapelId));

        if ($kdId !== null) {
            $query->where('kompetensi_dasar_id', $kdId);
        }

        $soalIds = $query->pluck('id')->all();

        if ($soalIds === []) {
            return null;
        }

        shuffle($soalIds);
        $soalIds = array_slice($soalIds, 0, max(1, $jumlahSoal));

        $batas = ($pilihan['timer'] ?? 'stopwatch') === 'countdown'
            ? (int) ($pilihan['batas_waktu_menit'] ?? 0)
            : null;

        return Percobaan::create([
            'user_id' => $user->getKey(),
            'jenis' => Percobaan::JENIS_LATIHAN,
            'paket_tryout_id' => null,
            'mapel_id' => $mapelId,
            'jumlah_soal' => count($soalIds),
            'batas_waktu_menit' => $batas,
            'status' => Percobaan::STATUS_BERJALAN,
            'daftar_soal' => [['mapel_id' => $mapelId, 'soal_ids' => $soalIds]],
            'urutan_mapel' => 0,
            'posisi_soal' => 0,
            'waktu_mulai' => now(),
        ]);
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
     * Batas waktu sudah terlampaui.
     *
     * Aplikasi tidak punya scheduler, jadi penutupan percobaan bergantung pada
     * request. Tiap masuk ke halaman pengerjaan atau mengirim jawaban,
     * kondisi ini dicek lebih dulu sehingga tryout tetap terkumpul walau
     * peserta menutup browser di tengah jalan.
     */
    public function kadaluarsa(Percobaan $percobaan): bool
    {
        if ($percobaan->batas_waktu_menit === null || $percobaan->waktu_mulai === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo(
            $percobaan->waktu_mulai->copy()->addMinutes($percobaan->batas_waktu_menit)
        );
    }

    /**
     * Menutup percobaan: menilai tiap jawaban, mengestimasi theta per mapel,
     * merata-ratanya (mapel yang tidak dijawab ditinggalkan, 7.4), menulis
     * ulang `tracking_kompetensi` per KD, lalu menyimpan ke `hasil_tryout`.
     *
     * Panggil berulang pun hasilnya tetap sama.
     *
     * Latihan tidak punya `hasil_tryout` (paketnya kosong), jadi yang kembali
     * dari metode ini `null`; penutupan tetap tercatat di `percobaan`,
     * `riwayat_pengerjaan`, dan `tracking_kompetensi`.
     */
    public function akhirkan(Percobaan $percobaan): ?HasilTryout
    {
        return DB::transaction(function () use ($percobaan): ?HasilTryout {
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

            // Latihan sudah ditutup tidak boleh dihitung ulang: penjumlahannya
            // memang idempoten, tetapi `waktu_selesai` dan durasinya harus
            // tetap milik percobaan pertama.
            if ($percobaan->status === Percobaan::STATUS_SELESAI && $percobaan->paket_tryout_id === null) {
                return null;
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

            $this->perbaruiTrackingKompetensi($percobaan->user, array_keys($indeksMapel));
            $this->perbaruiTrackingMapel($percobaan->user, $this->mapelTerlibat($percobaan));

            if ($percobaan->paket_tryout_id === null) {
                return null;
            }

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
     * Menulis ulang `tracking_kompetensi` untuk tiap KD yang tersentuh
     * percobaan ini, dihitung dari seluruh riwayat peserta pada KD itu.
     *
     * Dipanggil ulang, bukan ditambah, supaya idempoten: menutup percobaan dua
     * kali tidak menggandakan jumlah, dan theta selalu mencerminkan seluruh
     * jawaban yang pernah dikirim peserta pada KD tersebut (3.8, 3.9).
     *
     * @param  array<int, int>  $soalTerlibat
     */
    private function perbaruiTrackingKompetensi(User $user, array $soalTerlibat): void
    {
        if ($soalTerlibat === []) {
            return;
        }

        $kdIds = Soal::query()
            ->whereIn('id', $soalTerlibat)
            ->whereNotNull('kompetensi_dasar_id')
            ->distinct()
            ->pluck('kompetensi_dasar_id');

        $baris = [];

        foreach ($kdIds as $kdId) {
            $riwayat = RiwayatPengerjaan::query()
                ->with(['soal.opsiJawaban', 'soal.pernyataanKategori'])
                ->where('user_id', $user->getKey())
                ->whereHas('soal', fn ($q) => $q->where('kompetensi_dasar_id', $kdId))
                ->get();

            $dikerjakan = 0;
            $benar = 0;
            $items = [];

            foreach ($riwayat as $barisRiwayat) {
                if ($barisRiwayat->soal === null) {
                    continue;
                }

                $skor = $this->scoring->score($barisRiwayat->soal, $barisRiwayat->jawaban_user);

                if (($skor['jumlah_benar'] + $skor['jumlah_salah']) === 0) {
                    continue;
                }

                $dikerjakan++;

                if ($skor['jumlah_salah'] === 0 && $skor['jumlah_benar'] > 0) {
                    $benar++;
                }

                $items = [...$items, ...$this->keItemIrt(array_values(array_filter(
                    $skor['items'],
                    fn (array $item): bool => $item['resp'] !== null
                )))];
            }

            $theta = $items === []
                ? null
                : (count($items) < self::MIN_ITEM_MLE
                    ? $this->irt->estimateWithPrior($items)
                    : $this->irt->estimateMle($items));

            $baris[] = [
                'user_id' => $user->getKey(),
                'kompetensi_dasar_id' => (int) $kdId,
                'total_soal_dikerjakan' => $dikerjakan,
                'total_benar' => $benar,
                'persentase_benar' => $dikerjakan > 0 ? round($benar / $dikerjakan * 100, 2) : 0.0,
                'theta_estimasi' => $theta,
                'theta_se' => $theta === null ? null : $this->irt->standardError($theta, $items),
                'last_updated' => now(),
            ];
        }

        if ($baris === []) {
            return;
        }

        TrackingKompetensi::upsert(
            $baris,
            ['user_id', 'kompetensi_dasar_id'],
            ['total_soal_dikerjakan', 'total_benar', 'persentase_benar', 'theta_estimasi', 'theta_se', 'last_updated'],
        );
    }

    /**
     * Mapel yang masuk dalam daftar soal percobaan ini, tanpa duplikat.
     *
     * @return array<int, int>
     */
    private function mapelTerlibat(Percobaan $percobaan): array
    {
        $mapelIds = array_map(
            fn (array $grup): int => (int) $grup['mapel_id'],
            $percobaan->daftar_soal ?? [],
        );

        return array_values(array_unique($mapelIds));
    }

    /**
     * Menulis ulang `tracking_mapel` untuk tiap mapel yang tersentuh percobaan
     * ini, dihitung dari seluruh riwayat peserta pada mapel tersebut.
     *
     * Dipanggil ulang, bukan ditambah, supaya idempoten: menutup percobaan
     * berulang tidak menggandakan jumlah (3.9).
     *
     * `theta_estimasi` memakai seluruh item mapel — termasuk jawaban latihan —
     * agar analisis tidak kedaluwarsa sampai peserta ikut tryout berikutnya.
     * Sebaliknya `total_tryout_diikuti` dan `rata_rata_skor_irt` hanya dari
     * percobaan tryout, jadi latihan memperkaya theta tanpa pernah menaikkan
     * jumlah tryout.
     *
     * @param  array<int, int>  $mapelTerlibat
     */
    private function perbaruiTrackingMapel(User $user, array $mapelTerlibat): void
    {
        if ($mapelTerlibat === []) {
            return;
        }

        $perMapel = RiwayatPengerjaan::query()
            ->with(['soal.opsiJawaban', 'soal.pernyataanKategori', 'soal.kompetensiDasar'])
            ->where('user_id', $user->getKey())
            ->whereHas('soal.kompetensiDasar', fn ($q) => $q->whereIn('mapel_id', $mapelTerlibat))
            ->get()
            ->groupBy(fn (RiwayatPengerjaan $baris): int => (int) ($baris->soal?->kompetensiDasar?->mapel_id ?? 0));

        $baris = [];

        foreach ($mapelTerlibat as $mapelId) {
            $items = [];
            $skorTryout = [];
            $percobaanTryout = [];

            foreach ($perMapel->get((int) $mapelId, collect()) as $barisRiwayat) {
                if ($barisRiwayat->soal === null || $barisRiwayat->skor_irt === null) {
                    continue;
                }

                $skor = $this->scoring->score($barisRiwayat->soal, $barisRiwayat->jawaban_user);

                $items = [...$items, ...$this->keItemIrt(array_values(array_filter(
                    $skor['items'],
                    fn (array $item): bool => $item['resp'] !== null
                )))];

                if ($barisRiwayat->paket_tryout_id === null || $barisRiwayat->percobaan_id === null) {
                    continue;
                }

                $percobaanTryout[(int) $barisRiwayat->percobaan_id] = true;
                $skorTryout[] = (float) $barisRiwayat->skor_irt;
            }

            $theta = $items === []
                ? null
                : (count($items) < self::MIN_ITEM_MLE
                    ? $this->irt->estimateWithPrior($items)
                    : $this->irt->estimateMle($items));

            $baris[] = [
                'user_id' => $user->getKey(),
                'mapel_id' => (int) $mapelId,
                'theta_estimasi' => $theta,
                'level_kompetensi' => $this->level->levelFor($theta),
                'total_tryout_diikuti' => count($percobaanTryout),
                'rata_rata_skor_irt' => $skorTryout === []
                    ? null
                    : round(array_sum($skorTryout) / count($skorTryout), 3),
                'last_updated' => now(),
            ];
        }

        TrackingMapel::upsert(
            $baris,
            ['user_id', 'mapel_id'],
            ['theta_estimasi', 'level_kompetensi', 'total_tryout_diikuti', 'rata_rata_skor_irt', 'last_updated'],
        );
    }

    /**
     * Ringkasan per kompetensi dasar untuk satu percobaan, dipakai halaman hasil.
     *
     * Jumlah dan benar diambil dari percobaan ini saja, sedangkan theta dan
     * level dari `tracking_kompetensi` yang mengakumulasikan seluruh percobaan
     * peserta — supaya level tidak melompat-lompat antar tryout. KD yang belum
     * pernah dijawab tetap tampil dengan level "belum teridentifikasi" (7.5).
     *
     * @return array<int, array{kd: KompetensiDasar, jumlah: int, benar: int, theta: ?float, level: string, label: string}>
     */
    public function ringkasanKompetensi(Percobaan $percobaan): array
    {
        $soalIds = collect($percobaan->daftar_soal ?? [])
            ->flatMap(fn (array $grup): array => $grup['soal_ids'])
            ->values();

        $kdIds = Soal::query()
            ->whereIn('id', $soalIds)
            ->whereNotNull('kompetensi_dasar_id')
            ->distinct()
            ->pluck('kompetensi_dasar_id');

        if ($kdIds->isEmpty()) {
            return [];
        }

        $tracking = TrackingKompetensi::query()
            ->where('user_id', $percobaan->user_id)
            ->whereIn('kompetensi_dasar_id', $kdIds)
            ->get()
            ->keyBy('kompetensi_dasar_id');

        $perRiwayat = RiwayatPengerjaan::query()
            ->with('soal')
            ->where('percobaan_id', $percobaan->getKey())
            ->get()
            ->filter(fn (RiwayatPengerjaan $baris): bool => $baris->soal !== null)
            ->groupBy(fn (RiwayatPengerjaan $baris): int => (int) $baris->soal->kompetensi_dasar_id);

        return KompetensiDasar::query()
            ->whereIn('id', $kdIds)
            ->orderBy('kode_kompetensi')
            ->get()
            ->map(function (KompetensiDasar $kd) use ($perRiwayat, $tracking): array {
                $baris = $perRiwayat->get($kd->getKey());
                $theta = $tracking->get($kd->getKey())?->theta_estimasi;
                $level = $this->level->levelFor($theta);

                return [
                    'kd' => $kd,
                    'jumlah' => $baris?->count() ?? 0,
                    'benar' => $baris?->filter(fn (RiwayatPengerjaan $b): bool => $b->is_benar)->count() ?? 0,
                    'theta' => $theta,
                    'level' => $level,
                    'label' => $this->level->label($level),
                ];
            })
            ->all();
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
