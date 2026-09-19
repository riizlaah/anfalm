<x-layouts.app title="Manajemen Paket Soal">
    <h1 class="page-title">Manajemen Paket Soal</h1>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
        <a href="{{ route('admin.paket-soal.generate') }}" class="btn btn-ghost">Generate dari AI</a>
        <a href="{{ route('admin.paket-soal.create') }}" class="btn btn-primary">+ Tambah Paket</a>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">Nama Paket</th>
                    <th class="px-4 py-3">Mapel</th>
                    <th class="px-4 py-3">Jumlah Soal</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($paketSoals as $paket)
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">{{ $paket->nama_paket }}</td>
                        <td class="px-4 py-3">{{ $paket->mapel?->nama }}</td>
                        <td class="px-4 py-3">{{ $paket->soal_count }} soal</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.paket-soal.edit', $paket) }}" class="btn btn-ghost px-2.5 py-1 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.paket-soal.destroy', $paket) }}"
                                    onsubmit="return confirm('Hapus paket soal ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-slate-400">Belum ada paket soal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>