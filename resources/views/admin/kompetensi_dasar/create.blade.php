<x-layouts.app title="Tambah Kompetensi Dasar">
    <h1 class="page-title">Tambah Kompetensi Dasar</h1>

    <x-errors />

    <form method="POST" action="{{ route('admin.kompetensi-dasar.store') }}" class="card mt-5 max-w-lg space-y-4 p-6">
        @csrf

        <x-select label="Mapel" name="mapel_id" :value="old('mapel_id')" empty-option="-- pilih mapel --" required
            :options="$mapels->pluck('nama', 'id')" />

        <x-input label="Kode Kompetensi" name="kode_kompetensi" value="{{ old('kode_kompetensi') }}" required maxlength="50" />

        <x-textarea label="Deskripsi" name="deskripsi" value="{{ old('deskripsi') }}" required />

        <x-input label="Materi Pokok" name="materi_pokok" value="{{ old('materi_pokok') }}" maxlength="255" />

        <x-select label="Level Kognitif" name="level_kognitif" :value="old('level_kognitif')" required
            :options="['pengetahuan' => 'Pengetahuan', 'pemahaman' => 'Pemahaman', 'penerapan' => 'Penerapan', 'penalaran' => 'Penalaran']" />

        <x-textarea label="Batasan" name="batasan" value="{{ old('batasan') }}" placeholder="Opsional, untuk mempersempit cakupan materi" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.kompetensi-dasar.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>