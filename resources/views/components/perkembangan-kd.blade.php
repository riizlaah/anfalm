{{--
    Tabel level kompetensi per KD untuk halaman hasil tryout maupun latihan.

    @param array<int, array{kd: \App\Models\KompetensiDasar, jumlah: int, benar: int, theta: ?float, level: string, label: string}> $baris
--}}
<section class="card mt-5 p-6">
    <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
        Level kompetensi per KD
    </h2>

    <p class="mt-1 text-xs text-slate-500">
        Jumlah dan benar berasal dari percobaan ini. Theta dan level dihitung dari seluruh
        latihan dan tryout Anda pada kompetensi dasar itu, jadi tidak berubah tiap kali
        mengerjakan satu tryout. Belum pernah terjawab berarti belum teridentifikasi.
    </p>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs tracking-wide text-slate-500 uppercase">
                <tr class="border-b border-slate-200">
                    <th class="py-2 pr-4 font-medium">Kompetensi dasar</th>
                    <th class="py-2 pr-4 font-medium">Dikerjakan</th>
                    <th class="py-2 pr-4 font-medium">Benar</th>
                    <th class="py-2 pr-4 font-medium">Theta</th>
                    <th class="py-2 font-medium">Level</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($baris as $barisKd)
                    <tr>
                        <td class="py-2 pr-4 text-slate-700">
                            {{ $barisKd['kd']->deskripsi }}
                        </td>
                        <td class="py-2 pr-4 text-slate-700">{{ $barisKd['jumlah'] }}</td>
                        <td class="py-2 pr-4 text-slate-700">{{ $barisKd['benar'] }}</td>
                        <td class="py-2 pr-4 text-slate-700">
                            {{ $barisKd['theta'] !== null ? number_format((float) $barisKd['theta'], 3) : '—' }}
                        </td>
                        <td class="py-2 font-medium text-ink">{{ $barisKd['label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-slate-400">
                            Belum ada data kompetensi dasar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
