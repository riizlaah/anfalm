<x-layouts.app title="Manajemen Mapel">
    <h1 class="page-title">Manajemen Mapel</h1>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.mapel.index') }}" class="flex flex-wrap items-end gap-3">
            <x-select label="Tingkat" name="tingkat" :value="request('tingkat')" empty-option="Semua"
                :options="['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'SMK' => 'SMK', 'all' => 'Semua']"
                onchange="this.form.submit()" />

            <x-select label="Jenis" name="jenis" :value="request('jenis')" empty-option="Semua"
                :options="['wajib' => 'Wajib', 'pilihan_umum' => 'Pilihan Umum', 'pilihan_kejuruan' => 'Pilihan Kejuruan']"
                onchange="this.form.submit()" />
        </form>

        <a href="{{ route('admin.mapel.create') }}" class="btn btn-primary">+ Tambah Mapel</a>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">PKK</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($mapels as $mapel)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $mapel->kode }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ $mapel->nama }}</td>
                        <td class="px-4 py-3">{{ $mapel->tingkat === 'all' ? 'Semua' : $mapel->tingkat }}</td>
                        <td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $mapel->jenis)) }}</td>
                        <td class="px-4 py-3">{{ $mapel->is_pkk ? 'Ya' : 'Tidak' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.mapel.edit', $mapel) }}" class="btn btn-ghost px-2.5 py-1 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.mapel.destroy', $mapel) }}"
                                    onsubmit="return confirm('Hapus mapel ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-400">Belum ada mapel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>