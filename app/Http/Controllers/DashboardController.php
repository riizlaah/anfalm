<?php

namespace App\Http\Controllers;

use App\Domain\Aktivitas\KalenderAktivitas;
use App\Models\HasilTryout;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, KalenderAktivitas $kalender): View
    {
        $user = $request->user();

        // Admin tidak punya aktivitas belajar yang bisa dirangkum, jadi
        // kalendernya tidak dikirim ke tampilan sama sekali — supaya keputusan
        // "tampilkan untuk peserta" tetap berada di sini, bukan di Blade.
        $aktivitas = $user->isPeserta()
            ? $kalender->susun($this->jumlahAktivitasPerTanggal($user), now())
            : null;

        // Alasan yang sama: admin tidak mengerjakan tryout, jadi informasi
        // tryout pun tidak dikirim. Kartu itu hanya punya arti bagi peserta
        // yang bisa mengerjakannya.
        $tryoutTerbaru = $user->isPeserta()
            ? $this->tryoutTerbaru($user)
            : null;

        return view('dashboard', [
            'aktivitas' => $aktivitas,
            'tryoutTerbaru' => $tryoutTerbaru,
        ]);
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
