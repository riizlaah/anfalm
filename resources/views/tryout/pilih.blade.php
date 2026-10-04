<x-layouts.app title="Pilih Mapel Pilihan">
    <h1 class="page-title">Pilih 2 Mapel Pilihan</h1>

    <x-errors :only="['pilihan']" />

    <div class="card mt-5 max-w-3xl space-y-5 p-6">
        <p class="text-sm text-slate-600">
            {{ $paketTryout->nama_paket }} ·
            {{ $paketTryout->batas_waktu_menit }} menit ·
            Tingkat {{ $paketTryout->labelTingkat() }}
        </p>

        <div>
            <p class="label">Mapel wajib — ikut otomatis</p>

            <ul class="mt-2 flex flex-wrap gap-2">
                @foreach ($mapelWajib as $baris)
                    <li class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                        {{ $baris->mapel->nama }}
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($mapelPilihan->count() < 2)
            <p class="hint">
                Paket tryout ini baru menyediakan {{ $mapelPilihan->count() }} mapel pilihan,
                sedangkan tryout membutuhkan dua. Hubungi admin untuk melengkapi paketnya.
            </p>
        @else
            <form method="POST" action="{{ route('tryout.mulai', $paketTryout) }}" class="space-y-4">
                @csrf

                <div>
                    <p class="label">Mapel pilihan — pilih tepat dua</p>

                    <div class="mt-2 space-y-3">
                        {{-- Dikelompok per tingkat supaya siswa SMK dan SMA mudah
                             menemukan bagiannya; urutannya SMA, SMK, lalu sisanya. --}}
                        @foreach ($mapelPilihan
                            ->groupBy(fn ($baris) => $baris->mapel->tingkat)
                            ->sortBy(fn ($baris, $tingkat) => match ($tingkat) {
                                'SMA' => 0,
                                'SMK' => 1,
                                default => 2,
                            })
                            as $tingkat => $kelompok)
                            <div class="rounded-lg border border-slate-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $tingkat === 'all' ? 'Semua tingkat' : $tingkat }}
                                </p>

                                <div class="mt-2 space-y-2">
                                    @foreach ($kelompok as $baris)
                                        <label class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                                            <input type="checkbox" name="pilihan[]" value="{{ $baris->mapel_id }}"
                                                class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-ink"
                                                @checked(in_array((int) $baris->mapel_id, array_map('intval', old('pilihan', [])), true))>
                                            <span>
                                                {{ $baris->mapel->nama }}
                                                <span class="text-slate-500">
                                                    · {{ $baris->mapel->is_pkk ? 'PKK' : ($baris->mapel->jenis === 'pilihan_kejuruan' ? 'Kejuruan' : 'Umum') }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <x-button>Mulai Tryout</x-button>
                    <a href="{{ route('tryout.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
