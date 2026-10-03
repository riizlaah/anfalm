<x-layouts.app title="Manajemen Pengguna">
    <h1 class="page-title">Manajemen Pengguna</h1>

    {{-- Sengaja disebut di depan: keputusannya ada pada halaman ini, dan
         pengunjung yang datang mencari tombol hapus harus tahu sejak awal
         mengapa tombol itu tidak ada — bukan mengira halamannya rusak. --}}
    <p class="mt-1.5 text-sm text-slate-600">
        Ubah identitas, peran, dan kata sandi pengguna. Pengguna tidak bisa
        dihapus: riwayat tryout dan kompetensinya menempel pada akunnya.
    </p>

    <x-alert />

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Peran</th>
                    <th class="px-4 py-3">Sekolah</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $pengguna)
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">{{ $pengguna->nama_lengkap }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $pengguna->email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-ink">
                                {{ $pengguna->isAdmin() ? 'Admin' : 'Peserta' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $pengguna->sekolah ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                {{-- Baris sendiri memang tidak bisa dibuka
                                     (`tolakDiriSendiri`), jadi tautannya tidak
                                     ditampilkan — tombol yang selalu berujung
                                     pada pesan galat bukanlah tautan. --}}
                                @if ($pengguna->is(auth()->user()))
                                    <span class="text-xs text-slate-400">Akunmu sendiri</span>
                                @else
                                    <a href="{{ route('admin.user.edit', $pengguna) }}"
                                        class="btn btn-ghost px-2.5 py-1 text-xs">Ubah</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-slate-400">Belum ada pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
