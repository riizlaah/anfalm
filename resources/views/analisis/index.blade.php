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

            {{-- Kartu, bukan tabel: di layar 360px lima kolom angka harus
                 digulir mendatar sementara satu kartu muat apa adanya. Satu
                 kartu — yang levelnya paling rendah — disorot sebagai langkah
                 berikutnya. --}}
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($baris as $item)
                    <article @class([
                        'flex flex-col rounded-xl border p-4',
                        'border-amber-300 bg-amber-50 ring-1 ring-amber-300' => $item['fokus'],
                        'border-slate-200 bg-white' => ! $item['fokus'],
                    ]) @if ($item['fokus']) data-fokus="1" @endif>
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs text-slate-500">{{ $item['kd']->kode_kompetensi }}</span>
                            <span class="shrink-0 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-semibold text-ink">
                                {{ $item['label'] }}
                            </span>
                        </div>

                        <p class="mt-2 text-sm text-slate-700">{{ $item['kd']->deskripsi }}</p>

                        <p class="mt-3 text-xs text-slate-500">
                            {{ $item['persentase'] }}% benar · {{ $item['benar'] }}/{{ $item['dikerjakan'] }} soal
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Theta {{ $item['theta'] !== null ? number_format((float) $item['theta'], 3) : '—' }}
                        </p>

                        <p class="mt-2 text-xs text-slate-600">{{ $item['rekomendasi'] }}</p>

                        @if ($item['fokus'])
                            <p class="mt-3 text-xs font-semibold text-amber-700">Fokus berikutnya</p>
                        @endif

                        <div class="mt-auto pt-3">
                            @if ($item['jumlahSoal'] > 0)
                                <form method="POST" action="{{ route('latihan.mulai') }}">
                                    @csrf
                                    <input type="hidden" name="mapel_id" value="{{ $mapel->getKey() }}">
                                    <input type="hidden" name="kompetensi_dasar_id" value="{{ $item['kd']->getKey() }}">
                                    <input type="hidden" name="jumlah_soal" value="{{ min(10, $item['jumlahSoal']) }}">
                                    <input type="hidden" name="timer" value="stopwatch">
                                    <button type="submit" class="{{ ($item['fokus'] ? 'btn btn-primary' : 'btn btn-ghost') }} w-full" aria-label="Belajar {{ $item['kd']->kode_kompetensi }} — {{ $item['kd']->deskripsi }}">Belajar</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="py-4 text-center text-slate-400 sm:col-span-2 lg:col-span-3">
                        Belum ada data kompetensi dasar untuk mapel ini.
                    </p>
                @endforelse
            </div>
        </section>
    @endif

    @php
        $adaRiwayat = $grafik['riwayat']['labels'] !== [];
    @endphp

    <section class="card mt-5 p-6">
        <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            Grafik perkembangan
        </h2>

        {{--
            `grid-cols-1` wajib, bukan sekadar penanda. Tanpa kelas itu kolom
            tunggalnya berstatus `auto` sehingga ukuran jalurnya ikut min-content
            isi sel — dan isi selnya adalah kanvas Chart.js yang berlebar tetap
            523px. Setelah layar mengecil, kolomnya tak pernah bisa menyusut,
            Chart.js tak pernah menerima perintah resize, dan kartu grafik
            meluber ke luar viewport sampai halaman bisa digeser mendatar.
            `minmax(0, 1fr)` memutus lingkaran itu; `lg:grid-cols-2` sudah memakai
            bentuk yang sama.
        --}}
        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <figure>
                <figcaption class="label">Theta per mapel</figcaption>
                <div class="mt-2 h-64">
                    <canvas data-grafik="radar"
                        aria-label="Grafik radar perbandingan theta antar mapel"
                        role="img"></canvas>
                </div>
            </figure>

            @if ($mapel !== null)
                <figure>
                    <figcaption class="label">
                        Level per kompetensi dasar — {{ $mapel->nama }}
                    </figcaption>
                    <div class="mt-2 h-64">
                        <canvas data-grafik="level"
                            aria-label="Grafik batang perbandingan level tiap kompetensi dasar"
                            role="img"></canvas>
                    </div>
                </figure>
            @endif

            <figure @class(['lg:col-span-2' => $mapel === null || ! $adaRiwayat])>
                <figcaption class="label">Perkembangan theta tryout</figcaption>

                @if ($adaRiwayat)
                    <div class="mt-2 h-64">
                        <canvas data-grafik="riwayat"
                            aria-label="Grafik garis perkembangan theta dari waktu ke waktu"
                            role="img"></canvas>
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-400">
                        Belum ada tryout yang selesai. Grafik ini muncul setelah
                        Anda menyelesaikan tryout pertama.
                    </p>
                @endif
            </figure>
        </div>
    </section>

    <script type="application/json" id="grafik-analisis">@json($grafik)</script>
</x-layouts.app>
