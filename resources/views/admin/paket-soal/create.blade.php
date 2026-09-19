<x-layouts.app title="Tambah Paket Soal">
    <h1 class="page-title">Tambah Paket Soal</h1>

    <x-errors />

    <form method="POST" action="{{ route('admin.paket-soal.store') }}" class="card mt-5 max-w-3xl space-y-4 p-6">
        @csrf

        <x-admin.paket-soal-form :mapels="$mapels" :soals="$soals" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.paket-soal.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>