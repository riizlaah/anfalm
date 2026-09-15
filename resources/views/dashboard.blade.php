<x-layouts.app title="Dashboard">
    <section class="card max-w-3xl p-6 sm:p-8">
        <h1 class="page-title">Halo, {{ auth()->user()->nama_lengkap }}!</h1>

        @if (auth()->user()->isAdmin())
            <p class="mt-3 leading-relaxed text-slate-600">
                Anda masuk sebagai <strong class="font-semibold text-ink">Admin</strong>. Kelola konten dari
                <a href="{{ route('admin.dashboard') }}" class="link">area admin</a>.
            </p>
        @else
            <p class="mt-3 leading-relaxed text-slate-600">
                Selamat datang di platform tryout TKA. Pengerjaan tryout akan tersedia di sini.
            </p>
        @endif
    </section>
</x-layouts.app>