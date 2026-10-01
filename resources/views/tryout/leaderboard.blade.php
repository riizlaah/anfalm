<x-layouts.app title="Leaderboard">
    <h1 class="page-title">Leaderboard</h1>

    <x-alert />

    <p class="mt-2 max-w-2xl text-sm text-slate-600">
        Peringkat peserta yang sudah menyelesaikan
        <span class="font-medium text-ink">{{ $paketTryout->nama_paket }}</span>.
        Peserta yang sedang mengerjakan tidak ditampilkan. Seri skor dipecah oleh
        durasi pengerjaan, lalu oleh waktu selesai.
    </p>

    @if ($peringkatKe !== null)
        <p class="card mt-4 p-4 text-sm font-medium text-ink">
            Peringkat Anda: {{ $peringkatKe }} dari {{ $peringkat->count() }} peserta
        </p>
    @endif

    <div class="card mt-5 p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs tracking-wide text-slate-500 uppercase">
                    <tr class="border-b border-slate-200">
                        <th class="py-2 pr-4 font-medium">Peringkat</th>
                        <th class="py-2 pr-4 font-medium">Nama Peserta</th>
                        <th class="py-2 pr-4 font-medium">Skor IRT</th>
                        <th class="py-2 pr-4 font-medium">Jumlah Benar</th>
                        <th class="py-2 font-medium">Durasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($peringkat as $index => $hasil)
                        <tr @if ($peringkatKe === $index + 1) data-posisi-sendiri class="bg-slate-50" @endif>
                            <td class="py-2 pr-4 text-slate-700">{{ $index + 1 }}</td>
                            <td class="py-2 pr-4 text-slate-700">
                                {{ $hasil->user?->nama_lengkap ?? '—' }}

                                @if ($peringkatKe === $index + 1)
                                    <span class="ml-1 text-xs font-medium text-slate-400">(Anda)</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 font-medium text-ink">{{ $hasil->skor_konversi }}</td>
                            <td class="py-2 pr-4 text-slate-700">{{ $hasil->jumlah_benar }}</td>
                            <td class="py-2 text-slate-700">
                                {{ floor(($hasil->durasi_total ?? 0) / 60) }} menit
                                {{ ($hasil->durasi_total ?? 0) % 60 }} detik
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-slate-400">
                                Belum ada peserta yang menyelesaikan tryout ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-4">
        <a href="{{ route('tryout.index') }}" class="link">Kembali ke daftar tryout</a>
    </p>
</x-layouts.app>
