<?php

namespace App\Http\Controllers;

use App\Domain\Aktivitas\KalenderAktivitas;
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

        return view('dashboard', ['aktivitas' => $aktivitas]);
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
