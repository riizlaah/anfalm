<x-layouts.app title="Manajemen Paket Tryout">
    <h1 class="page-title">Manajemen Paket Tryout</h1>

    <x-alert />

    <div class="mt-5 flex justify-end">
        <a href="{{ route('admin.paket-tryout.create') }}" class="btn btn-primary">+ Tambah Tryout</a>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">Nama Tryout</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-4 py-3">Batas Waktu</th>
                    <th class="px-4 py-3">Mapel</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($paketTryouts as $paketTryout)
                    @php
                        $namaMapel = collect([
                            $paketTryout->wajib1,
                            $paketTryout->wajib2,
                            $paketTryout->wajib3,
                            $paketTryout->pilihan1,
                            $paketTryout->pilihan2,
                        ])->filter()->pluck('nama')->implode(', ');
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">{{ $paketTryout->nama_paket }}</td>
                        <td class="px-4 py-3">{{ $paketTryout->tingkat }}</td>
                        <td class="px-4 py-3">{{ $paketTryout->batas_waktu_menit }} menit</td>
                        <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($namaMapel, 80) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.paket-tryout.edit', $paketTryout) }}" class="btn btn-ghost px-2.5 py-1 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.paket-tryout.destroy', $paketTryout) }}"
                                    onsubmit="return confirm('Hapus paket tryout ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-slate-400">Belum ada paket tryout.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>