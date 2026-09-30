<x-layouts.app :title="'Edit Soal · '.$mapel->nama">
    <a href="{{ route('admin.mapel.soal.index', $mapel) }}" class="link text-sm">&larr; Kembali ke Manajemen Soal</a>

    <h1 class="page-title mt-2">Edit Soal</h1>
    <p class="mt-1 text-sm text-slate-600">
        Mapel <span class="font-semibold text-ink">{{ $mapel->nama }}</span> ({{ $mapel->kode }})
    </p>

    <x-errors />

    <form method="POST" action="{{ route('admin.mapel.soal.update', [$mapel, $soal]) }}" class="card mt-5 max-w-3xl p-6" id="soal-form">
        @csrf
        @method('PUT')

        <x-admin.soal-form :soal="$soal" :kompetensi-dasars="$kompetensiDasars" />

        <div class="flex items-center gap-3 pt-1">
            <x-button>Simpan</x-button>
            <a href="{{ route('admin.mapel.soal.index', $mapel) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
