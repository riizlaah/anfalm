<x-layouts.app title="Edit Paket Tryout">
    <h1 class="page-title">Edit Paket Tryout</h1>

    <x-errors />

    <form method="POST" action="{{ route('admin.paket-tryout.update', $paketTryout) }}" class="card mt-5 max-w-4xl space-y-4 p-6">
        @csrf
        @method('PUT')

        <x-admin.paket-tryout-form :paketTryout="$paketTryout" :mapels="$mapels" :paketSoals="$paketSoals" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.paket-tryout.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>