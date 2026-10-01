<x-layouts.app title="Analisis Kompetensi">
    <h1 class="page-title">Analisis Kompetensi</h1>

    <x-alert />

    <p class="mt-2 max-w-2xl text-sm text-slate-600">
        Kekuatan dan kelemahan Anda per kompetensi dasar, dihitung dari seluruh
        latihan dan tryout yang pernah Anda selesaikan.
    </p>

    <form method="GET" action="{{ route('analisis.index') }}" class="card mt-5 p-5">
        <x-select label="Mapel" name="mapel_id" :value="$mapel?->getKey()"
            :options="$mapels->mapWithKeys(fn ($pilihan): array => [$pilihan->getKey() => $pilihan->nama])"
            onchange="this.form.submit()" />
    </form>

    @if ($mapel === null)
        <p class="card mt-5 p-6 text-center text-sm text-slate-400">
            Belum ada mapel yang tersedia.
        </p>
    @endif

    @if ($ringkasan !== null)
        <section class="card mt-5 p-6">
            <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Ringkasan peta kompetensi
            </h2>

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="label">Theta</dt>
                    <dd class="text-lg font-semibold text-ink">
                        {{ $ringkasan->theta_estimasi !== null
                            ? number_format((float) $ringkasan->theta_estimasi, 3)
                            : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="label">Level</dt>
                    <dd class="text-lg font-semibold text-ink">{{ $labelRingkasan ?: '—' }}</dd>
                </div>

                <div>
                    <dt class="label">Jumlah tryout</dt>
                    <dd class="text-lg font-semibold text-ink">{{ $ringkasan->total_tryout_diikuti }}</dd>
                </div>

                <div>
                    <dt class="label">Rata-rata skor</dt>
                    <dd class="text-lg font-semibold text-ink">
                        {{ $ringkasan->rata_rata_skor_irt !== null
                            ? number_format((float) $ringkasan->rata_rata_skor_irt, 3)
                            : '—' }}
                    </dd>
                </div>
            </dl>

            <p class="hint">
                Latihan menaikkan theta dan level, tetapi tidak menambah jumlah tryout
                maupun rata-rata skor — keduanya hanya dihitung dari tryout yang selesai.
            </p>
        </section>
    @endif

    @if ($mapel !== null)
        <section class="card mt-5 p-6">
            <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Analisis per kompetensi dasar — {{ $mapel->nama }}
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Persentase dihitung dari soal yang sudah Anda kerjakan pada KD tersebut.
                Theta ditampilkan hingga tiga desimal; belum pernah terjawab berarti
                belum teridentifikasi.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs tracking-wide text-slate-500 uppercase">
                        <tr class="border-b border-slate-200">
                            <th class="py-2 pr-4 font-medium">Kompetensi dasar</th>
                            <th class="py-2 pr-4 font-medium">Level</th>
                            <th class="py-2 pr-4 font-medium">Persentase</th>
                            <th class="py-2 pr-4 font-medium">Theta</th>
                            <th class="py-2 font-medium">Rekomendasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($baris as $barisKd)
                            <tr>
                                <td class="py-2 pr-4 text-slate-700">
                                    {{ $barisKd['kd']->kode_kompetensi }} — {{ $barisKd['kd']->deskripsi }}
                                </td>
                                <td class="py-2 pr-4 font-medium text-ink">
                                    {{ $barisKd['label'] }}
                                </td>
                                <td class="py-2 pr-4 text-slate-700">
                                    {{ $barisKd['persentase'] }}%
                                    <span class="block text-xs text-slate-400">
                                        {{ $barisKd['benar'] }}/{{ $barisKd['dikerjakan'] }} soal
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-slate-700">
                                    {{ $barisKd['theta'] !== null
                                        ? number_format((float) $barisKd['theta'], 3)
                                        : '—' }}
                                </td>
                                <td class="py-2 text-slate-600">
                                    {{ $barisKd['rekomendasi'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-slate-400">
                                    Belum ada data kompetensi dasar untuk mapel ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
