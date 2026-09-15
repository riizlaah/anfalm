<x-layouts.app title="Tambah Soal">
    <h1 class="page-title">Tambah Soal</h1>

    <x-errors />

    <form method="POST" action="{{ route('admin.soal.store') }}" class="card mt-5 max-w-3xl p-6" id="soal-form">
        @csrf

        <x-admin.soal-form :soal="null" :kompetensi-dasars="$kompetensiDasars" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.soal.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>