<x-layouts.app title="Hasil Latihan">
    @php
        $total = $riwayat->count();
        $kosong = $riwayat->filter(fn ($baris) => $baris->skor_irt === null)->count();
        $salah = $total - $jumlahBenar - $kosong;
    @endphp

    <h1 class="page-title">Hasil Latihan</h1>

    <p class="mt-1 text-sm text-slate-600">{{ $mapel?->nama }} · {{ $total }} soal</p>

    <x-alert />

    <section class="card mt-5 p-6">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-slate-500">Jawaban benar</dt>
                <dd class="text-lg font-semibold text-ink">{{ $jumlahBenar }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Jawaban salah</dt>
                <dd class="text-lg font-semibold text-ink">{{ $salah }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Tidak dijawab</dt>
                <dd class="text-lg font-semibold text-ink">{{ $kosong }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Durasi</dt>
                <dd class="text-lg font-semibold text-ink">
                    {{ floor(($percobaan->durasi_detik ?? 0) / 60) }} menit
                </dd>
            </div>
        </dl>
    </section>

    <x-perkembangan-kd :baris="$perKd" />

    <section class="mt-5">
        <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pembahasan</h2>

        <div class="mt-3 space-y-4">
            @foreach ($riwayat as $index => $baris)
                @if ($baris->soal === null)
                    @continue
                @endif

                @php
                    $soal = $baris->soal;
                    $terkirim = $baris->jawaban_user ?? [];
                    $adaJawaban = $baris->skor_irt !== null;
                @endphp

                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="text-sm font-medium text-slate-800">
                            {{ $index + 1 }}. {!! nl2br(e($soal->pertanyaan)) !!}
                        </p>

                        @unless ($adaJawaban)
                            <span class="text-xs font-semibold text-slate-400">Tidak dijawab</span>
                        @else
                            <span class="text-xs font-semibold {{ $baris->is_benar ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $baris->is_benar ? 'Benar' : 'Salah' }}
                            </span>
                        @endunless
                    </div>

                    @if ($soal->isPGKategori())
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($soal->pernyataanKategori as $pernyataan)
                                <li class="text-slate-700">
                                    <span class="font-medium">{{ $pernyataan->teks_pernyataan }}</span><br>
                                    Jawabanmu: {{ $terkirim['kategori'][$pernyataan->id] ?? '—' }}
                                    · Kunci: {{ $pernyataan->kategori_benar }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($soal->opsiJawaban as $opsi)
                                @php
                                    $dipilih = in_array($opsi->id, (array) ($terkirim['opsi'] ?? []));
                                @endphp

                                <li @class([
                                    'font-semibold text-emerald-700' => $opsi->is_benar,
                                    'text-rose-600' => $dipilih && ! $opsi->is_benar,
                                    'text-slate-700' => ! $opsi->is_benar && ! $dipilih,
                                ])>
                                    {{ $opsi->is_benar ? '✓' : ($dipilih ? '✗' : '') }}
                                    {{ $opsi->teks_opsi }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($soal->pembahasan)
                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pembahasan</p>

                            {{-- Konten pembahasan masih teks polos; rendering
                                 WYSIWYG + KaTeX menyusul pada Fase 8. --}}
                            <div class="mt-1 text-sm leading-relaxed text-slate-700">
                                {!! nl2br(e($soal->pembahasan)) !!}
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <div class="mt-6 flex flex-wrap justify-end gap-2">
        <a href="{{ route('latihan.index') }}" class="btn btn-ghost">Latihan lagi</a>
        <a href="{{ route('tryout.index') }}" class="btn btn-ghost">Ke daftar tryout</a>
    </div>
</x-layouts.app>
