<x-layouts.app title="Manajemen Kompetensi Dasar">
    <h1 class="page-title">Manajemen Kompetensi Dasar</h1>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.kompetensi-dasar.index') }}" class="flex flex-wrap items-end gap-3">
            <x-select label="Mapel" name="mapel_id" :value="request('mapel_id')" empty-option="Semua"
                :options="$mapels->pluck('nama', 'id')" onchange="this.form.submit()" />

            <x-select label="Level Kognitif" name="level_kognitif" :value="request('level_kognitif')" empty-option="Semua"
                :options="['pengetahuan' => 'Pengetahuan', 'pemahaman' => 'Pemahaman', 'penerapan' => 'Penerapan', 'penalaran' => 'Penalaran']"
                onchange="this.form.submit()" />
        </form>

        <a href="{{ route('admin.kompetensi-dasar.create') }}" class="btn btn-primary">+ Tambah KD</a>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">Mapel</th>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Deskripsi</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Batasan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($kompetensiDasars as $kd)
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">{{ $kd->mapel->nama }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $kd->kode_kompetensi }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($kd->deskripsi, 60) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($kd->level_kognitif) }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($kd->batasan, 30) ?: '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.kompetensi-dasar.edit', $kd) }}" class="btn btn-ghost px-2.5 py-1 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.kompetensi-dasar.destroy', $kd) }}"
                                    onsubmit="return confirm('Hapus KD ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-400">Belum ada kompetensi dasar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>