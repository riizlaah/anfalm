<x-layouts.app title="Tryout">
    <h1 class="page-title">Paket Tryout</h1>

    <x-alert />

    <div class="mt-5 space-y-4">
        @forelse ($paketTryouts as $paketTryout)
            @php
                $adaHasil = $sudahDinilai->has($paketTryout->getKey());
                $masihBerjalan = $berjalan->has($paketTryout->getKey());
            @endphp

            <article class="card p-5">
                {{-- `md:` dipakai supaya sejajar dengan ambang nav (md:flex) — di
                     antara 640–767px baris masih menumpuk, karena grup tombol
                     butuh ±390px dan judul sempat terdesak bila dipaksa sejajar. --}}
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-ink">{{ $paketTryout->nama_paket }}</h2>

                        @if ($paketTryout->deskripsi)
                            <p class="mt-1 text-sm text-slate-600">{{ $paketTryout->deskripsi }}</p>
                        @endif

                        <p class="mt-2 text-xs text-slate-500">
                            Tingkat {{ $paketTryout->labelTingkat() }} ·
                            {{ $paketTryout->batas_waktu_menit }} menit ·
                            {{ $paketTryout->namaMapel() }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 md:shrink-0 md:justify-end">
                        <a href="{{ route('tryout.leaderboard', $paketTryout) }}" class="btn btn-ghost">Leaderboard</a>

                        @if ($adaHasil)
                            <a href="{{ route('tryout.hasil', $paketTryout) }}" class="btn btn-ghost">Lihat Hasil</a>
                        @elseif ($masihBerjalan)
                            <form method="POST" action="{{ route('tryout.ulang', $paketTryout) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost">Mulai Ulang</button>
                            </form>

                            <a href="{{ route('tryout.kerja', $paketTryout) }}" class="btn btn-primary">Lanjutkan Tryout</a>
                        @else
                            {{-- Peserta memilih sendiri dua mapel pilihan pada
                                 halaman berikutnya sebelum percobaan dibuat. --}}
                            <a href="{{ route('tryout.pilih', $paketTryout) }}" class="btn btn-primary">Mulai Tryout</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="card p-10 text-center text-slate-400">Belum ada paket tryout yang tersedia.</p>
        @endforelse
    </div>
</x-layouts.app>
