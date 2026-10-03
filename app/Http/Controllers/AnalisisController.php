<?php

namespace App\Http\Controllers;

use App\Domain\Scoring\IrtService;
use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\Soal;
use App\Models\TrackingKompetensi;
use App\Models\TrackingMapel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman "Analisis Kompetensi" peserta (3.9).
 *
 * Memetakan `tracking_kompetensi` per KD dan `tracking_mapel` per mapel menjadi
 * level, persentase, skor IRT, dan rekomendasi latihan. Payload ketiga grafik
 * (radar, batang, garis) ikut dikirim dan dirender Chart.js di sisi klien.
 *
 * Theta tidak pernah sampai ke tampilan sebagai angka mentah: ia skala internal
 * −3…+3 yang hanya bermakna di kalibrasi soal ini. Yang dibaca peserta adalah
 * terjemahannya — Skor IRT untuk angka dan Level untuk kategori.
 */
class AnalisisController extends Controller
{
    public function __construct(
        private readonly KompetensiLevel $kompetensi,
        private readonly IrtService $irt,
    ) {}

    public function index(Request $request): View
    {
        $peserta = $request->user();

        // Pilihan mapel peserta (butir 96) hanya menyaring *tampilan*: daftar
        // radar dan pilihan dropdown memakai yang ini, sedangkan ringkasan di
        // bawahnya tetap dihitung dari seluruh pengerjaannya di semua mapel.
        $mapels = $peserta->mapelTampil(Mapel::query()
            ->whereNull('deleted_at')
            ->orderBy('kode')
            ->get());

        $mapel = $mapels->firstWhere('id', (int) $request->query('mapel_id'))
            ?? $mapels->first();

        $baris = collect();
        $ringkasan = null;

        if ($mapel !== null) {
            $baris = $this->barisPerKd($peserta, $mapel);

            $ringkasan = TrackingMapel::query()
                ->where('user_id', $peserta->getKey())
                ->where('mapel_id', $mapel->getKey())
                ->first();
        }

        return view('analisis.index', [
            'mapels' => $mapels,
            'mapel' => $mapel,
            'baris' => $baris,
            'ringkasan' => $ringkasan,
            'grafik' => $this->grafik($peserta, $mapels, $baris),
            'labelRingkasan' => $ringkasan === null
                ? null
                : $this->kompetensi->label($ringkasan->level_kompetensi ?? ''),
            // Terjemahan theta untuk ringkasan: skor dalam skala pelaporan
            // akun, dan rata-rata proporsi jawaban benar (rentang 0–1) sebagai
            // persentase — angka 0–1 tidak pernah dibaca siapa pun apa adanya.
            'skorIrt' => $ringkasan?->theta_estimasi === null
                ? null
                : $this->irt->convertToScale((float) $ringkasan->theta_estimasi, $peserta->tingkat),
            'rataRataBenar' => $ringkasan?->rata_rata_skor_irt === null
                ? null
                : (int) round((float) $ringkasan->rata_rata_skor_irt * 100),
        ]);
    }

    /**
     * Baris analisis untuk tiap KD pada `$mapel`. KD yang belum pernah
     * dijawab tetap tampil, lengkap dengan level "Belum Teridentifikasi" dan
     * rekomendasi latihan awal.
     *
     * `jumlahSoal` dipakai halaman untuk menahan tombol Belajar pada KD yang
     * belum punya soal — memunculkannya hanya berujung pada pesan galat
     * "Mapel ini belum punya soal" setelah diklik.
     *
     * @return Collection<int, array{kd: KompetensiDasar, dikerjakan: int, benar: int, persentase: float, theta: float|null, label: string, rekomendasi: string, urut: int, jumlahSoal: int, fokus: bool}>
     */
    private function barisPerKd(User $peserta, Mapel $mapel): Collection
    {
        $kds = $mapel->kompetensiDasars()
            ->orderBy('kode_kompetensi')
            ->get(['id', 'kode_kompetensi', 'deskripsi']);

        $terlacak = TrackingKompetensi::query()
            ->where('user_id', $peserta->getKey())
            ->whereIn('kompetensi_dasar_id', $kds->pluck('id'))
            ->get()
            ->keyBy('kompetensi_dasar_id');

        $jumlahSoal = Soal::query()
            ->whereIn('kompetensi_dasar_id', $kds->pluck('id'))
            ->groupBy('kompetensi_dasar_id')
            ->selectRaw('kompetensi_dasar_id, COUNT(*) as jumlah')
            ->pluck('jumlah', 'kompetensi_dasar_id');

        $baris = $kds->map(function (KompetensiDasar $kd) use ($terlacak, $jumlahSoal): array {
            $data = $terlacak->get($kd->getKey());
            $theta = $data?->theta_estimasi;
            $level = $this->kompetensi->levelFor($theta);

            return [
                'kd' => $kd,
                'dikerjakan' => (int) ($data?->total_soal_dikerjakan ?? 0),
                'benar' => (int) ($data?->total_benar ?? 0),
                'persentase' => (float) ($data?->persentase_benar ?? 0),
                'theta' => $theta,
                'label' => $this->kompetensi->label($level),
                'rekomendasi' => $this->kompetensi->rekomendasiFor($theta),
                'urut' => $this->kompetensi->urut($level),
                'jumlahSoal' => (int) ($jumlahSoal[$kd->getKey()] ?? 0),
                'fokus' => false,
            ];
        });

        return $this->tetapkanFokus($baris);
    }

    /**
     * Menandai tepat satu KD sebagai fokus belajar berikutnya: level terendah,
     * lalu yang paling sedikit dikerjakan, lalu kode terkecil supaya hasilnya
     * deterministik. KD tanpa soal tidak pernah terpilih karena tombol Belajar
     * tidak ditampilkan untuknya.
     *
     * @param  Collection<int, array{kd: KompetensiDasar, urut: int, dikerjakan: int, jumlahSoal: int, fokus: bool}>  $baris
     * @return Collection<int, array{kd: KompetensiDasar, dikerjakan: int, benar: int, persentase: float, theta: float|null, label: string, rekomendasi: string, urut: int, jumlahSoal: int, fokus: bool}>
     */
    private function tetapkanFokus(Collection $baris): Collection
    {
        $kandidat = $baris->filter(fn (array $b): bool => $b['jumlahSoal'] > 0);

        $fokus = $kandidat->sortBy([
            fn (array $a, array $b): int => $a['urut'] <=> $b['urut'],
            fn (array $a, array $b): int => $a['dikerjakan'] <=> $b['dikerjakan'],
            fn (array $a, array $b): int => strcmp(
                (string) $a['kd']->kode_kompetensi,
                (string) $b['kd']->kode_kompetensi,
            ),
        ])->first();

        if ($fokus === null) {
            return $baris;
        }

        return $baris->map(function (array $b) use ($fokus): array {
            $b['fokus'] = $b['kd']->getKey() === $fokus['kd']->getKey();

            return $b;
        });
    }

    /**
     * Payload ketiga grafik halaman analisis (3.9 butir 3–4).
     *
     * - `radar`: skor IRT per mapel milik peserta.
     * - `level`: perbandingan level tiap KD pada mapel terpilih, dipetakan ke
     *   skala ordinal 0–4 agar bisa dibandingkan secara visual.
     * - `riwayat`: skor IRT tiap tryout yang sudah selesai, terurut waktu.
     *
     * `radar` dan `riwayat` membawa `batas`: rentang skala pelaporan akun yang
     * diturunkan dari konversi theta ekstrem. Sumbunya jadi berdiri pada nilai
     * yang benar-benar bisa dicapai — peran yang dulu dipegang −3…+3 sebelum
     * theta diterjemahkan — sehingga selisih kecil antar mapel tidak ikut
     * melebar sendiri mengikuti data.
     *
     * @param  Collection<int, array{kd: KompetensiDasar, theta: float|null, label: string}>  $baris
     * @return array<string, array<string, array<int, mixed>>>
     */
    private function grafik(User $peserta, Collection $mapels, Collection $baris): array
    {
        $terlacak = TrackingMapel::query()
            ->where('user_id', $peserta->getKey())
            ->get()
            ->keyBy('mapel_id');

        return [
            'radar' => [
                'labels' => $mapels->pluck('nama')->values()->all(),
                // Kode dipakai label sumbu di kanvas sempit; nama lengkap tetap
                // ada di `labels` supaya tooltip tidak kehilangan informasi.
                'singkat' => $mapels->pluck('kode')->values()->all(),
                'skor' => $mapels
                    ->map(fn (Mapel $m): ?int => $this->skorDari(
                        $terlacak->get($m->getKey())?->theta_estimasi,
                        $peserta->tingkat,
                    ))
                    ->values()
                    ->all(),
                'batas' => $this->irt->rentangSkor($peserta->tingkat),
            ],
            'level' => [
                'labels' => $baris->pluck('kd.kode_kompetensi')->values()->all(),
                'nilai' => $baris
                    ->map(fn (array $b): int => $this->kompetensi->urut(
                        $this->kompetensi->levelFor($b['theta']),
                    ))
                    ->values()
                    ->all(),
                'level' => $baris->pluck('label')->values()->all(),
            ],
            'riwayat' => $this->riwayatTryout($peserta),
        ];
    }

    /**
     * Terjemahan theta ke Skor IRT untuk satuan sumbu grafik. Theta yang belum
     * terisi tetap `null`, bukan nol — nol adalah angka, dan angka nol di grafik
     * berarti "kemampuan paling rendah", padahal belum ada data sama sekali.
     */
    private function skorDari(?float $theta, ?string $tingkat): ?int
    {
        return $theta === null
            ? null
            : $this->irt->convertToScale($theta, $tingkat);
    }

    /**
     * Riwayat skor IRT per tryout yang sudah selesai, diurutkan dari yang
     * paling lama agar grafik garisnya terbaca sebagai perkembangan waktu.
     *
     * @return array{labels: array<int, string>, skor: array<int, int>, batas: array{0: int, 1: int}}
     */
    private function riwayatTryout(User $peserta): array
    {
        $riwayat = HasilTryout::query()
            ->where('user_id', $peserta->getKey())
            ->orderBy('selesai_pada')
            ->get(['theta_final', 'selesai_pada']);

        return [
            'labels' => $riwayat
                ->map(fn (HasilTryout $hasil): string => $hasil->selesai_pada->format('d/m/Y'))
                ->values()
                ->all(),
            'skor' => $riwayat
                ->map(fn (HasilTryout $hasil): int => $this->irt->convertToScale(
                    (float) $hasil->theta_final,
                    $peserta->tingkat,
                ))
                ->values()
                ->all(),
            'batas' => $this->irt->rentangSkor($peserta->tingkat),
        ];
    }
}
