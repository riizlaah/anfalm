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

    {{--
        Kartu dalam grid, bukan tabel lima kolom yang harus digulir mendatar di
        layar 360px. Urutan DOM tetap peringkat, jadi di layar sempit kartu
        pertama memang peringkat satu; di layar lebar baris pertama menjadi
        tiga peringkat teratas. Baris milik peserta sendiri tetap disorot dan
        tetap membawa atribut `data-posisi-sendiri`.
    --}}
    <div class="card mt-5 p-6">
        <ol class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($peringkat as $index => $hasil)
                <li @class([
                    'rounded-xl border p-4',
                    'border-amber-300 bg-amber-50 ring-1 ring-amber-300' => $peringkatKe === $index + 1,
                    'border-slate-200 bg-white' => $peringkatKe !== $index + 1,
                ]) @if ($peringkatKe === $index + 1) data-posisi-sendiri @endif>
                    <div class="flex items-start justify-between gap-2 text-xs text-slate-500">
                        <span>Peringkat {{ $index + 1 }}</span>
                        <span class="shrink-0">
                            {{ floor(($hasil->durasi_total ?? 0) / 60) }} menit
                            {{ ($hasil->durasi_total ?? 0) % 60 }} detik
                        </span>
                    </div>

                    <p class="mt-2 text-sm font-semibold text-ink">
                        {{ $hasil->user?->nama_lengkap ?? '—' }}

                        @if ($peringkatKe === $index + 1)
                            <span class="ml-1 text-xs font-medium text-slate-400">(Anda)</span>
                        @endif
                    </p>

                    <p class="mt-3 text-xs text-slate-500">
                        Skor IRT {{ $hasil->skor_konversi }} · {{ $hasil->jumlah_benar }} benar
                    </p>
                </li>
            @empty
                <li class="py-4 text-center text-slate-400 sm:col-span-2 lg:col-span-3">
                    Belum ada peserta yang menyelesaikan tryout ini.
                </li>
            @endforelse
        </ol>
    </div>

    <p class="mt-4">
        <a href="{{ route('tryout.index') }}" class="link">Kembali ke daftar tryout</a>
    </p>
</x-layouts.app>
