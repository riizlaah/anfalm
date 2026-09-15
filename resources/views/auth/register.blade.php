<x-layouts.auth title="Daftar">
    <h1 class="mb-1 text-xl font-bold tracking-tight text-ink">Daftar Akun</h1>
    <p class="mb-5 text-sm text-slate-500">Buat akun sebagai peserta untuk mulai berlatih.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-input label="Nama Lengkap" name="nama_lengkap" value="{{ old('nama_lengkap') }}" required autofocus />

        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" />

        <x-input label="Kata Sandi (min. 8 karakter)" name="password" type="password" required autocomplete="new-password" />

        <x-input label="Konfirmasi Kata Sandi" name="password_confirmation" type="password" required autocomplete="new-password" />

        <x-errors />

        <x-button type="submit" class="w-full">Daftar</x-button>
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">
        Sudah punya akun? <a href="{{ route('login') }}" class="link">Masuk</a>
    </p>
</x-layouts.auth>