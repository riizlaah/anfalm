{{--
    Daftar pembahasan per soal untuk halaman hasil tryout maupun latihan (3.7).

    Peserta melihat pertanyaan, opsi yang dipilih, kunci jawaban, lalu pembahasan.
    Konten sudah dirender WYSIWYG; ekspresi KaTeX-nya di-render oleh JavaScript
    pada setiap elemen bertanda `data-rumus`.

    @param \Illuminate\Support\Collection<int, \App\Models\RiwayatPengerjaan> $riwayat
--}}
@props(['riwayat'])

@php
    // Baris tanpa soal (soal sudah terhapus) dilewati, dan values() membuat
    // nomor urut tetap rapat walau ada yang terlewat.
    $terisi = $riwayat->filter(fn ($baris) => $baris->soal !== null)->values();
@endphp

@if ($terisi->isNotEmpty())
    <section class="mt-5">
        <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pembahasan</h2>

        <div class="mt-3 space-y-4">
            @foreach ($terisi as $index => $baris)
                @php
                    $soal = $baris->soal;
                    $terkirim = $baris->jawaban_user ?? [];
                    $adaJawaban = $baris->skor_irt !== null;
                @endphp

                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        {{-- div, bukan p: konten WYSIWYG berupa `<p>`, dan `<p>`
                             bersarang akan ditutup paksa oleh parser. --}}
                        <div class="text-sm font-medium text-slate-800" data-rumus>
                            {{ $index + 1 }}. <x-konten :isi="$soal->pertanyaan" />
                        </div>

                        @unless ($adaJawaban)
                            <span class="text-xs font-semibold text-slate-400">Tidak dijawab</span>
                        @else
                            <span class="text-xs font-semibold {{ $baris->is_benar ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $baris->is_benar ? 'Benar' : 'Salah' }}
                            </span>
                        @endunless
                    </div>

                    @if ($soal->isPGKategori())
                        {{-- Bentuk yang sama dengan halaman pengerjaan, diganti
                             dari kontrol radio menjadi penanda jawaban/kunci. --}}
                        <x-matriks-kategori :soal="$soal" :jawaban="$terkirim['kategori'] ?? []" tinjau />
                    @else
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($soal->opsiJawaban as $opsi)
                                @php
                                    $dipilih = in_array($opsi->id, (array) ($terkirim['opsi'] ?? []));
                                @endphp

                                <li data-rumus @class([
                                    'font-semibold text-emerald-700' => $opsi->is_benar,
                                    'text-rose-600' => $dipilih && ! $opsi->is_benar,
                                    'text-slate-700' => ! $opsi->is_benar && ! $dipilih,
                                ])>
                                    {{ $opsi->is_benar ? '✓' : ($dipilih ? '✗' : '') }}
                                    <x-konten :isi="$opsi->teks_opsi" />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($soal->pembahasan)
                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pembahasan</p>

                            <div class="mt-1 text-sm leading-relaxed text-slate-700" data-rumus>
                                <x-konten :isi="$soal->pembahasan" />
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
