<x-layouts.app title="Tambah Mapel">
    <h1 class="page-title">Tambah Mapel</h1>

    <x-errors />

    <form method="POST" action="{{ route('admin.mapel.store') }}" class="card mt-5 max-w-lg space-y-4 p-6">
        @csrf

        <x-input label="Kode" name="kode" value="{{ old('kode') }}" required maxlength="20" />

        <x-input label="Nama" name="nama" value="{{ old('nama') }}" required maxlength="100" />

        <x-select label="Tingkat" name="tingkat" :value="old('tingkat')" required
            :options="['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'SMK' => 'SMK', 'all' => 'Semua']" />

        <x-select label="Jenis" name="jenis" :value="old('jenis')" required
            :options="['wajib' => 'Wajib', 'pilihan_umum' => 'Pilihan Umum', 'pilihan_kejuruan' => 'Pilihan Kejuruan']" />

        <x-checkbox name="is_pkk" label="Proyek Kreatif & Kewirausahaan (PKK)" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.mapel.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>