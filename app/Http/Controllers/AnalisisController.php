<?php

namespace App\Http\Controllers;

use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
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
 * level, persentase, theta, dan rekomendasi latihan. Payload ketiga grafik
 * (radar, batang, garis) ikut dikirim dan dirender Chart.js di sisi klien.
 */
class AnalisisController extends Controller
{
    public function __construct(private readonly KompetensiLevel $kompetensi) {}

    public function index(Request $request): View
    {
        $peserta = $request->user();

        $mapels = Mapel::query()
            ->whereNull('deleted_at')
            ->orderBy('kode')
            ->get();

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
        ]);
    }

    /**
     * Baris tabel analisis untuk tiap KD pada `$mapel`. KD yang belum pernah
     * dijawab tetap tampil, lengkap dengan level "Belum Teridentifikasi" dan
     * rekomendasi latihan awal.
     *
     * @return Collection<int, array{kd: KompetensiDasar, dikerjakan: int, benar: int, persentase: float, theta: float|null, label: string, rekomendasi: string}>
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

        return $kds->map(function (KompetensiDasar $kd) use ($terlacak): array {
            $data = $terlacak->get($kd->getKey());
            $theta = $data?->theta_estimasi;

            return [
                'kd' => $kd,
                'dikerjakan' => (int) ($data?->total_soal_dikerjakan ?? 0),
                'benar' => (int) ($data?->total_benar ?? 0),
                'persentase' => (float) ($data?->persentase_benar ?? 0),
                'theta' => $theta,
                'label' => $this->kompetensi->label($this->kompetensi->levelFor($theta)),
                'rekomendasi' => $this->kompetensi->rekomendasiFor($theta),
            ];
        });
    }

    /**
     * Payload ketiga grafik halaman analisis (3.9 butir 3–4).
     *
     * - `radar`: theta per mapel milik peserta.
     * - `level`: perbandingan level tiap KD pada mapel terpilih, dipetakan ke
     *   skala ordinal 0–4 agar bisa dibandingkan secara visual.
     * - `riwayat`: theta akhir tiap tryout yang sudah selesai, terurut waktu.
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
                'theta' => $mapels
                    ->map(fn (Mapel $m): ?float => $terlacak->get($m->getKey())?->theta_estimasi)
                    ->values()
                    ->all(),
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
     * Riwayat theta akhir per tryout yang sudah selesai, diurutkan dari yang
     * paling lama agar grafik garisnya terbaca sebagai perkembangan waktu.
     *
     * @return array{labels: array<int, string>, theta: array<int, float>}
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
            'theta' => $riwayat->pluck('theta_final')->values()->all(),
        ];
    }
}
