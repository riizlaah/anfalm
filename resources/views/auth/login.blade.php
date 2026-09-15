<x-layouts.auth title="Masuk">
    <h1 class="mb-1 text-xl font-bold tracking-tight text-ink">Masuk</h1>
    <p class="mb-5 text-sm text-slate-500">Lanjutkan tryout kamu dengan masuk ke akun.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" />

        <x-input label="Kata Sandi" name="password" type="password" required autocomplete="current-password" />

        <x-errors />

        <x-button type="submit" class="w-full">Masuk</x-button>
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">
        Belum punya akun? <a href="{{ route('register') }}" class="link">Daftar</a>
    </p>
</x-layouts.auth>