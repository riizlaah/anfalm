<x-layouts.app title="Area Admin">
    <h1 class="page-title">Dashboard Admin</h1>
    <p class="mt-1.5 text-slate-600">Kelola master data untuk penyusunan tryout.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('admin.mapel.index') }}"
            class="card group p-5 transition hover:border-ink/40 hover:shadow-md">
            <h2 class="text-lg font-semibold text-ink group-hover:text-ink-soft">Mapel</h2>
            <p class="mt-1 text-sm text-slate-500">Daftar mata pelajaran</p>
        </a>

        <a href="{{ route('admin.kompetensi-dasar.index') }}"
            class="card group p-5 transition hover:border-ink/40 hover:shadow-md">
            <h2 class="text-lg font-semibold text-ink group-hover:text-ink-soft">Kompetensi Dasar</h2>
            <p class="mt-1 text-sm text-slate-500">KD per mapel</p>
        </a>

        <a href="{{ route('admin.soal.index') }}"
            class="card group p-5 transition hover:border-ink/40 hover:shadow-md">
            <h2 class="text-lg font-semibold text-ink group-hover:text-ink-soft">Soal</h2>
            <p class="mt-1 text-sm text-slate-500">Bank soal PG / PG Kompleks / PG Kategori</p>
        </a>

        <a href="{{ route('admin.paket-soal.index') }}"
            class="card group p-5 transition hover:border-ink/40 hover:shadow-md">
            <h2 class="text-lg font-semibold text-ink group-hover:text-ink-soft">Paket Soal</h2>
            <p class="mt-1 text-sm text-slate-500">Kumpulan soal satu mapel</p>
        </a>

        <a href="{{ route('admin.paket-tryout.index') }}"
            class="card group p-5 transition hover:border-ink/40 hover:shadow-md">
            <h2 class="text-lg font-semibold text-ink group-hover:text-ink-soft">Paket Tryout</h2>
            <p class="mt-1 text-sm text-slate-500">3 mapel wajib + 2 pilihan</p>
        </a>
    </div>
</x-layouts.app>