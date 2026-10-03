<x-layouts.app title="Edit Pengguna">
    <h1 class="page-title">Edit Pengguna</h1>

    <p class="mt-1 text-sm text-slate-600">
        {{ $pengguna->nama_lengkap }} · {{ $pengguna->email }}
    </p>

    <x-errors />

    {{--
        Hanya email, nama, dan peran — tiga hal yang tidak bisa diubah sendiri
        lewat halaman profil (`ProfilController::update()` tidak pernah
        menerima keduanya). Sekolah, tingkat, dan jurusan sengaja tidak ikut:
        sudah bisa diubah pemiliknya, dan mengulangnya di sini hanya membuat
        dua tempat yang bisa tidak sinkron.
    --}}
    <form method="POST" action="{{ route('admin.user.update', $pengguna) }}" class="card mt-5 max-w-2xl space-y-4 p-6">
        @csrf
        @method('PUT')

        <x-input label="Nama lengkap" name="nama_lengkap" :value="$pengguna->nama_lengkap" required maxlength="100" />

        <x-input label="Email" name="email" type="email" :value="$pengguna->email" required maxlength="255" />

        <x-select label="Peran" name="role" :value="$pengguna->role" required
            :options="['admin' => 'Admin', 'peserta' => 'Peserta']" />

        <p class="hint">
            Peran menentukan siapa yang boleh mengelola konten. Menurunkan diri
            sendiri dari sini tidak dimungkinkan — lihat catatan di bawah.
        </p>

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.user.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>

    {{--
        Kartu terpisah, bukan bagian dari form data: keduanya punya akibat yang
        berbeda dan tidak boleh ikut terkirim bersamaan. Menekan "Simpan" tidak
        boleh pernah mereset kata sandi orang.
    --}}
    <section class="card mt-6 max-w-2xl p-6">
        <p class="label">Reset kata sandi</p>

        <p class="text-sm text-slate-600">
            Kata sandi baru langsung berlaku, dan seluruh sesi pengguna ini
            diminta masuk kembali — termasuk yang sedang berjalan di perangkat
            lain.
        </p>

        <form method="POST" action="{{ route('admin.user.reset-password', $pengguna) }}" class="mt-4 space-y-4">
            @csrf

            <x-input label="Kata sandi baru" name="password" type="password" required autocomplete="new-password" />

            <x-input label="Ulangi kata sandi" name="password_confirmation" type="password" required autocomplete="new-password" />

            <div class="flex items-center gap-3 pt-1">
                <x-button>Reset kata sandi</x-button>
                <a href="{{ route('admin.user.index') }}" class="btn btn-ghost">Kembali</a>
            </div>
        </form>
    </section>
</x-layouts.app>
