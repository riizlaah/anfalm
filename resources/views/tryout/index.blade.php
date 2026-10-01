<x-layouts.app title="Tryout">
    <h1 class="page-title">Paket Tryout</h1>

    <x-alert />

    <div class="mt-5 space-y-4">
        @forelse ($paketTryouts as $paketTryout)
            @php
                $namaMapel = collect([
                    $paketTryout->wajib1,
                    $paketTryout->wajib2,
                    $paketTryout->wajib3,
                    $paketTryout->pilihan1,
                    $paketTryout->pilihan2,
                ])->filter()->pluck('nama')->implode(', ');

                $adaHasil = $sudahDinilai->has($paketTryout->getKey());
                $masihBerjalan = $berjalan->has($paketTryout->getKey());
            @endphp

            <article class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-ink">{{ $paketTryout->nama_paket }}</h2>

                        @if ($paketTryout->deskripsi)
                            <p class="mt-1 text-sm text-slate-600">{{ $paketTryout->deskripsi }}</p>
                        @endif

                        <p class="mt-2 text-xs text-slate-500">
                            Tingkat {{ $paketTryout->tingkat }} ·
                            {{ $paketTryout->batas_waktu_menit }} menit ·
                            {{ $namaMapel }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
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
                            <form method="POST" action="{{ route('tryout.mulai', $paketTryout) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Mulai Tryout</button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="card p-10 text-center text-slate-400">Belum ada paket tryout yang tersedia.</p>
        @endforelse
    </div>
</x-layouts.app>
