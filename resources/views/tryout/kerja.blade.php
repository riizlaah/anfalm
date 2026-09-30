<x-layouts.app :title="$paketTryout->nama_paket">
    @php
        $terakhirMapel = $posisiMapel === $totalMapel;

        // Pakai jawaban yang tadi dikirim ulang bila ada error validasi, kalau
        // tidak pakai yang sudah tersimpan supaya peserta bisa melanjutkan.
        $jawabanSementara = old('jawaban');
        if (! is_array($jawabanSementara)) {
            $jawabanSementara = [
                'opsi' => $jawabanTersimpan
                    ->map(fn ($baris) => $baris->jawaban_user['opsi'] ?? null)
                    ->filter()
                    ->all(),
                'kategori' => $jawabanTersimpan
                    ->map(fn ($baris) => $baris->jawaban_user['kategori'] ?? null)
                    ->filter()
                    ->all(),
            ];
        }
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $mapel->nama }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $paketTryout->nama_paket }} · Mapel {{ $posisiMapel }} dari {{ $totalMapel }}
            </p>
        </div>
    </div>

    <x-alert />

    <form method="POST" action="{{ route('tryout.jawab', $paketTryout) }}">
        @csrf

        <div class="mt-5 space-y-4">
            @foreach ($soals as $index => $soal)
                @php
                    $kunci = $soal->isPGKategori() ? 'kategori' : 'opsi';
                    $jawabanSoal = $jawabanSementara[$kunci][$soal->id] ?? null;
                @endphp

                <section class="card p-5" id="soal-{{ $soal->id }}">
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Soal {{ $index + 1 }} dari {{ $soals->count() }}
                    </p>

                    {{-- Konten soal masih teks polos; penyuntingan HTML dan
                         sanitasinya menyusul bersama editor pada Fase 8. --}}
                    <div class="mt-2 text-sm leading-relaxed text-slate-800">
                        {!! nl2br(e($soal->pertanyaan)) !!}
                    </div>

                    @if ($soal->isPGKategori())
                        <div class="mt-4 space-y-3">
                            @foreach ($soal->pernyataanKategori as $pernyataan)
                                <div>
                                    <p class="text-sm text-slate-700">{{ $pernyataan->teks_pernyataan }}</p>

                                    <select name="jawaban[kategori][{{ $soal->id }}][{{ $pernyataan->id }}]"
                                        class="select mt-1 w-auto">
                                        <option value="">Pilih kategori…</option>
                                        @foreach ((array) $soal->daftar_kategori as $kategori)
                                            <option value="{{ $kategori }}"
                                                @selected(($jawabanSoal[$pernyataan->id] ?? null) === $kategori)>
                                                {{ $kategori }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @php
                            $opsiTerpilih = (array) $jawabanSoal;
                            $satuJawaban = $soal->isPG();
                        @endphp

                        <div class="mt-4 space-y-2">
                            @foreach ($soal->opsiJawaban as $opsi)
                                <label class="check">
                                    <input type="{{ $satuJawaban ? 'radio' : 'checkbox' }}"
                                        name="jawaban[opsi][{{ $soal->id }}]{{ $satuJawaban ? '' : '[]' }}"
                                        value="{{ $opsi->id }}"
                                        @checked(in_array($opsi->id, $opsiTerpilih))>
                                    <span>{{ $opsi->teks_opsi }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn btn-primary">
                {{ $terakhirMapel ? 'Selesai & Lihat Hasil' : 'Lanjut ke Mapel Berikutnya' }}
            </button>
        </div>
    </form>
</x-layouts.app>
