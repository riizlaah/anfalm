<?php

namespace App\Http\Controllers;

use App\Domain\Aktivitas\KalenderAktivitas;
use App\Domain\Scoring\KompetensiLevel;
use App\Models\HasilTryout;
use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\TrackingMapel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly KalenderAktivitas $kalender,
        private readonly KompetensiLevel $kompetensi,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // Admin tidak punya aktivitas belajar yang bisa dirangkum, jadi
        // kalendernya tidak dikirim ke tampilan sama sekali — supaya keputusan
        // "tampilkan untuk peserta" tetap berada di sini, bukan di Blade.
        $aktivitas = $user->isPeserta()
            ? $this->kalender->susun($this->jumlahAktivitasPerTanggal($user), now())
            : null;

        // Alasan yang sama: admin tidak mengerjakan tryout, jadi informasi
        // tryout pun tidak dikirim. Kartu itu hanya punya arti bagi peserta
        // yang bisa mengerjakannya.
        $tryoutTerbaru = $user->isPeserta()
            ? $this->tryoutTerbaru($user)
            : null;

        $kartuLatihan = $user->isPeserta()
            ? $this->kartuLatihan($user)
            : null;

        return view('dashboard', [
            'aktivitas' => $aktivitas,
            'tryoutTerbaru' => $tryoutTerbaru,
            'kartuLatihan' => $kartuLatihan,
        ]);
    }

    /**
     * Satu kartu latihan per mapel wajib beserta mapel pilihan yang dipilih
     * peserta ini, lengkap dengan ajakan dan label tombol menurut levelnya.
     *
     * Daftarnya sengaja `mapelTerpilih()`, **bukan** `mapelTampil()`: aturan
     * "kosong = semua" akan menumbuhkan sepuluh kartu untuk peserta yang
     * belum memilih apa pun, padahal kartu ini justru merangkum yang sedang
     * ia kejar. Karena pilihan dibatasi dua, jumlah kartunya pun berhenti
     * dengan sendirinya — wajib selalu ada, pilihan maksimum dua.
     *
     * Levelnya dibaca dari `tracking_mapel.level_kompetensi`, kolom yang sama
     * dipakai ringkasan halaman Analisis, supaya peserta tidak pernah
     * melihat dua keadaan berbeda untuk mapel yang sama di dua halaman.
     * Baris yang belum ada dianggap belum teridentifikasi. θ tidak pernah
     * dikirim ke tampilan (aturan butir B1) — hanya label levelnya.
     *
     * @return Collection<int, array{mapel: Mapel, level: string, label: string, ajakan: string, tombol: string}>
     */
    private function kartuLatihan(User $user): Collection
    {
        $terlacak = TrackingMapel::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('mapel_id');

        return $user
            ->mapelTerpilih(Mapel::query()
                ->whereNull('deleted_at')
                ->orderBy('kode')
                ->get())
            ->map(function (Mapel $mapel) use ($terlacak): array {
                $level = $terlacak->get($mapel->getKey())?->level_kompetensi
                    ?: KompetensiLevel::BELUM_TERIDENTIFIKASI;

                return [
                    'mapel' => $mapel,
                    'level' => $level,
                    'label' => $this->kompetensi->label($level),
                    ...$this->kompetensi->kartuLatihan($level),
                ];
            })
            ->values();
    }

    /**
     * Paket tryout terbaru beserta hasil peserta ini pada paket itu.
     *
     * "Terbaru" dibaca dari id, bukan dari urutan nama seperti halaman daftar
     * tryout: yang dicari adalah paket yang paling baru dibuat admin, dan
     * urutan abjad sama sekali tidak berkaitan dengan kapan ia dibuat.
     * `hasil` bernilai null ketika peserta belum mengerjakannya — perbedaan
     * itulah yang mengubah isi kartu di tampilan.
     *
     * @return array{paket: ?PaketTryout, hasil: ?HasilTryout}
     */
    private function tryoutTerbaru(User $user): array
    {
        $paket = PaketTryout::query()
            ->with(['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'])
            ->latest('id')
            ->first();

        return [
            'paket' => $paket,
            'hasil' => $paket === null
                ? null
                : HasilTryout::query()
                    ->where('user_id', $user->getKey())
                    ->where('paket_tryout_id', $paket->getKey())
                    ->first(),
        ];
    }

    /**
     * Jumlah percobaan per tanggal, dihitung sebagai agregat di basis data
     * supaya seluruh riwayat muat dalam satu baris per hari tanpa memuat
     * setiap barisnya.
     *
     * @return array<string, int>
     */
    private function jumlahAktivitasPerTanggal(User $user): array
    {
        return Percobaan::toBase()
            ->selectRaw('DATE(waktu_mulai) AS tanggal, COUNT(*) AS jumlah')
            ->where('user_id', $user->getKey())
            ->whereNotNull('waktu_mulai')
            ->groupByRaw('DATE(waktu_mulai)')
            ->get()
            ->mapWithKeys(fn (object $baris): array => [
                Carbon::parse($baris->tanggal)->toDateString() => (int) $baris->jumlah,
            ])
            ->all();
    }
}
