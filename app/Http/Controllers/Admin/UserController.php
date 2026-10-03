<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman kelola pengguna (butir A3).
 *
 * Halaman ini menutup satu-satunya identitas yang tidak bisa diubah lewat
 * halaman profil: `ProfilController::update()` menerima nama lengkap, sekolah,
 * tingkat dan jurusan, tetapi tidak pernah `email` maupun `role`. Karena itu
 * tiga bidang itulah yang ditawarkan form di sini, ditambah reset kata sandi
 * — permintaan praktis yang selalu menyertai halaman manajemen akun.
 *
 * Dua ketidakhadiran disengaja dan bukan pekerjaan yang tertinggal:
 * **tidak ada hapus**, dan **akun yang sedang dibuka tidak bisa berisi akun
 * sendiri**. Alasan keduanya dijelaskan pada `tolakDiriSendiri()`.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.user.index', [
            'users' => User::query()->orderBy('nama_lengkap')->get(),
        ]);
    }

    public function edit(Request $request, User $user): View|RedirectResponse
    {
        if ($ditolak = $this->tolakDiriSendiri($request, $user)) {
            return $ditolak;
        }

        return view('admin.user.edit', ['pengguna' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($ditolak = $this->tolakDiriSendiri($request, $user)) {
            return $ditolak;
        }

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PESERTA])],
        ]);

        $user->update($data);

        return redirect()->route('admin.user.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($ditolak = $this->tolakDiriSendiri($request, $user)) {
            return $ditolak;
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            // Memutar token sesi adalah cara aplikasi mematikan seluruh sesi
            // yang sedang berjalan: `EnsureSingleSession` membandingkan token
            // di sesi dengan kolom ini, jadi begitu keduanya berbeda pengguna
            // langsung diminta masuk kembali. Tanpa putaran ini, reset kata
            // sandi hanya mengganti berkas — peserta yang lupa kata sandi tetap
            // duduk di sesi lamanya dengan kata sandi yang sudah tidak berlaku.
            'session_token' => Str::random(64),
        ])->save();

        return redirect()->route('admin.user.index')
            ->with('success', 'Kata sandi berhasil direset. Pengguna diminta masuk kembali.');
    }

    /**
     * Satu aturan menutup dua bahaya sekaligus. Pertama: admin terakhir yang
     * menurunkan perannya sendiri tidak menyisakan siapa pun yang bisa
     * menaikkannya kembali, karena hanya admin yang bisa membuka halaman ini.
     * Kedua: memutar `session_token` pada akun sendiri mengeluarkan admin itu
     * dari aplikasi tepat di tengah pekerjaannya. Mengelola akun sendiri juga
     * bukan yang ditanyakan butir ini — yang tidak bisa diubah peserta adalah
     * identitas *orang lain*.
     */
    private function tolakDiriSendiri(Request $request, User $user): ?RedirectResponse
    {
        if (! $user->is($request->user())) {
            return null;
        }

        return redirect()->route('admin.user.index')
            ->with('error', 'Akunmu sendiri tidak bisa diubah dari halaman ini.');
    }
}
