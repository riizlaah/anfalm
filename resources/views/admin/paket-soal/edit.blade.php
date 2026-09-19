<x-layouts.app title="Edit Paket Soal">
    <h1 class="page-title">Edit Paket Soal</h1>

    <x-errors />

    <form method="POST" action="{{ route('admin.paket-soal.update', $paketSoal) }}" class="card mt-5 max-w-3xl space-y-4 p-6">
        @csrf
        @method('PUT')

        <x-admin.paket-soal-form :paket="$paketSoal" :mapels="$mapels" :soals="$soals" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.paket-soal.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>