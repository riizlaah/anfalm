<x-layouts.app :title="$paketTryout->nama_paket">
    @php
        $terakhirMapel = $posisiMapel === $totalMapel;

        $batasAkhir = $percobaan->batas_waktu_menit !== null && $percobaan->waktu_mulai !== null
            ? $percobaan->waktu_mulai->copy()->addMinutes($percobaan->batas_waktu_menit)
            : null;

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

        @if ($batasAkhir)
            <p class="text-sm text-slate-600">
                Sisa waktu
                <time id="hitung-mundur" datetime="{{ $batasAkhir->toIso8601String() }}"
                    data-batas="{{ $batasAkhir->toIso8601String() }}"
                    class="ml-1 font-semibold text-ink">—</time>
            </p>
        @endif
    </div>

    <x-alert />

    <form method="POST" action="{{ route('tryout.jawab', $paketTryout) }}" id="form-tryout">
        @csrf
        <input type="hidden" name="aksi" id="aksi-input" value="lanjut">

        <nav class="card mt-5 flex flex-wrap items-center gap-2 p-3" aria-label="Navigasi soal">
            @foreach ($soals as $index => $soal)
                <button type="button" class="rail-soal" data-indeks="{{ $index }}"
                    data-terjawab="0" data-aktif="{{ $index === 0 ? '1' : '0' }}"
                    aria-label="Lompat ke soal {{ $index + 1 }}">
                    {{ $index + 1 }}
                </button>
            @endforeach
        </nav>

        <div class="mt-4 space-y-4">
            @foreach ($soals as $index => $soal)
                @php
                    $kunci = $soal->isPGKategori() ? 'kategori' : 'opsi';
                    $jawabanSoal = $jawabanSementara[$kunci][$soal->id] ?? null;
                @endphp

                <section class="card p-5" data-blok-soal="{{ $index }}" @if ($index > 0) hidden @endif>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Soal {{ $index + 1 }} dari {{ $soals->count() }}
                    </p>

                    {{-- Konten soal masih teks polos; penyuntingan HTML dan
                         sanitasinya menyusul bersama editor pada Fase 8. --}}
                    <div class="text-sm leading-relaxed text-slate-800">
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

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="btn btn-ghost" id="soal-sebelumnya" disabled>Sebelumnya</button>

            <span class="text-sm text-slate-500" id="posisi-soal">Soal 1 dari {{ $soals->count() }}</span>

            <button type="button" class="btn btn-ghost" id="soal-berikutnya">Berikutnya</button>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" class="btn btn-primary" data-dialog-open="konfirmasi-mapel">
                {{ $terakhirMapel ? 'Selesai & Lihat Hasil' : 'Lanjut ke Mapel Berikutnya' }}
            </button>
        </div>
    </form>

    <x-dialog id="konfirmasi-mapel"
        :title="$terakhirMapel ? 'Selesaikan mapel terakhir?' : 'Pindah ke mapel berikutnya?'">
        <p class="text-sm text-slate-600">
            Jawaban di mapel <strong>{{ $mapel->nama }}</strong> akan disimpan dan mapel ini
            dikunci — peserta tidak bisa kembali lagi.
            @unless ($terakhirMapel)
                Pastikan semua soal sudah dijawab.
            @endunless
        </p>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            <button type="submit" form="form-tryout" class="btn btn-primary">
                {{ $terakhirMapel ? 'Ya, selesaikan' : 'Ya, lanjut' }}
            </button>
        </div>
    </x-dialog>

    <script>
        (function () {
            const form = document.getElementById('form-tryout');
            const aksiInput = document.getElementById('aksi-input');
            const blok = Array.from(form.querySelectorAll('[data-blok-soal]'));
            const rail = Array.from(form.querySelectorAll('[data-indeks]'));
            const tombolSebelum = document.getElementById('soal-sebelumnya');
            const tombolBerikut = document.getElementById('soal-berikutnya');
            const posisiSoal = document.getElementById('posisi-soal');
            let posisi = 0;

            function terjawab(index) {
                const isi = blok[index].querySelectorAll('input[type=radio], input[type=checkbox], select');

                for (const input of isi) {
                    if (input.type === 'radio' || input.type === 'checkbox') {
                        if (input.checked) {
                            return true;
                        }
                    } else if (input.value !== '') {
                        return true;
                    }
                }

                return false;
            }

            function perbaruiRail() {
                rail.forEach((tombol, index) => {
                    tombol.dataset.terjawab = terjawab(index) ? '1' : '0';
                    tombol.dataset.aktif = index === posisi ? '1' : '0';
                });
            }

            function tampilkan(index) {
                posisi = Math.max(0, Math.min(blok.length - 1, index));
                blok.forEach((b, n) => { b.hidden = n !== posisi; });
                posisiSoal.textContent = 'Soal ' + (posisi + 1) + ' dari ' + blok.length;
                tombolSebelum.disabled = posisi === 0;
                tombolBerikut.disabled = posisi === blok.length - 1;
                perbaruiRail();
            }

            rail.forEach((tombol) => {
                tombol.addEventListener('click', () => tampilkan(Number(tombol.dataset.indeks)));
            });

            tombolSebelum.addEventListener('click', () => tampilkan(posisi - 1));
            tombolBerikut.addEventListener('click', () => tampilkan(posisi + 1));

            form.addEventListener('change', perbaruiRail);

            // Hitung mundur global. Basisnya waktu mulai di server, jadi muat
            // ulang halaman tidak mengulang waktu dari nol.
            const hitungMundur = document.getElementById('hitung-mundur');

            if (hitungMundur) {
                const batas = new Date(hitungMundur.dataset.batas).getTime();
                let sudahDikirim = false;

                const tik = () => {
                    const sisa = Math.max(0, Math.ceil((batas - Date.now()) / 1000));
                    const menit = String(Math.floor(sisa / 60)).padStart(2, '0');
                    const detik = String(sisa % 60).padStart(2, '0');
                    hitungMundur.textContent = menit + ':' + detik;

                    if (sisa > 0) {
                        window.setTimeout(tik, 1000);

                        return;
                    }

                    if (!sudahDikirim) {
                        sudahDikirim = true;
                        aksiInput.value = 'selesai';
                        form.requestSubmit();
                    }
                };

                tik();
            }
        })();
    </script>
</x-layouts.app>
