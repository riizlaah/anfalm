{{--
    Halaman pengerjaan bersama untuk tryout dan latihan.

    Keduanya punya bentuk yang sama: satu halaman berisi semua soal mapel yang
    sedang aktif, rail navigasi, dan timer. Yang membedakan hanya label, tujuan
    form, dan waktu yang ditampilkan — percobaan punya hitung mundur, latihan
    yang stopwatch berjalan naik.

    @param string $title
    @param string $judul
    @param string $subjudul
    @param string $action
    @param string $aksiDefault
    @param string $labelKirim
    @param array{judul: string, isi: string, tombol: string} $konfirmasi
    @param array{label: string, aksi: string}|null $simpan
--}}
<x-layouts.app :title="$title">
    @php
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
            <h1 class="page-title">{{ $judul }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $subjudul }}</p>
        </div>

        @if ($batasAkhir)
            <p class="text-sm text-slate-600">
                Sisa waktu
                <time id="hitung-mundur" datetime="{{ $batasAkhir->toIso8601String() }}"
                    data-batas="{{ $batasAkhir->toIso8601String() }}"
                    class="ml-1 font-semibold text-ink">—</time>
            </p>
        @elseif ($percobaan->waktu_mulai)
            <p class="text-sm text-slate-600">
                Waktu berjalan
                <time id="stopwatch" datetime="{{ $percobaan->waktu_mulai->toIso8601String() }}"
                    data-mulai="{{ $percobaan->waktu_mulai->toIso8601String() }}"
                    class="ml-1 font-semibold text-ink">00:00</time>
            </p>
        @endif
    </div>

    <x-alert />

    <form method="POST" action="{{ $action }}" id="form-percobaan">
        @csrf
        <input type="hidden" name="aksi" id="aksi-input" value="{{ $aksiDefault }}">

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
                <section class="card p-5" data-blok-soal="{{ $index }}" @if ($index > 0) hidden @endif>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Soal {{ $index + 1 }} dari {{ $soals->count() }}
                    </p>

                    <x-soal-isi :soal="$soal" :jawaban="$jawabanSementara" />
                </section>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="btn btn-ghost" id="soal-sebelumnya" disabled>Sebelumnya</button>

            <span class="text-sm text-slate-500" id="posisi-soal">Soal 1 dari {{ $soals->count() }}</span>

            <button type="button" class="btn btn-ghost" id="soal-berikutnya">Berikutnya</button>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
            @if ($simpan)
                <button type="submit" name="aksi" value="{{ $simpan['aksi'] }}" class="btn btn-ghost">
                    {{ $simpan['label'] }}
                </button>
            @endif

            <button type="button" class="btn btn-primary" data-dialog-open="konfirmasi-pengerjaan">
                {{ $labelKirim }}
            </button>
        </div>
    </form>

    <x-dialog id="konfirmasi-pengerjaan" :title="$konfirmasi['judul']">
        <p class="text-sm text-slate-600">{{ $konfirmasi['isi'] }}</p>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>

            {{-- Tombol ini sengaja tanpa name/value: aksi datang dari input
                 tersembunyi di atas, karena tombolnya berada di luar form. --}}
            <button type="submit" form="form-percobaan" class="btn btn-primary">
                {{ $konfirmasi['tombol'] }}
            </button>
        </div>
    </x-dialog>

    <script>
        (function () {
            const form = document.getElementById('form-percobaan');
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

            function duaDigit(n) {
                return String(n).padStart(2, '0');
            }

            // Hitung mundur global. Basisnya waktu mulai di server, jadi muat
            // ulang halaman tidak mengulang waktu dari nol.
            const hitungMundur = document.getElementById('hitung-mundur');

            if (hitungMundur) {
                const batas = new Date(hitungMundur.dataset.batas).getTime();
                let sudahDikirim = false;

                const tik = () => {
                    const sisa = Math.max(0, Math.ceil((batas - Date.now()) / 1000));
                    hitungMundur.textContent = duaDigit(Math.floor(sisa / 60)) + ':' + duaDigit(sisa % 60);

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

            // Stopwatch: hanya mencatat durasi, tidak pernah menghentikan
            // pengerjaan sendiri.
            const stopwatch = document.getElementById('stopwatch');

            if (stopwatch) {
                const mulai = new Date(stopwatch.dataset.mulai).getTime();

                const detik = () => Math.max(0, Math.floor((Date.now() - mulai) / 1000));
                const tampil = () => {
                    const berjalan = detik();
                    stopwatch.textContent = duaDigit(Math.floor(berjalan / 60)) + ':' + duaDigit(berjalan % 60);
                };

                tampil();
                window.setInterval(tampil, 1000);
            }
        })();
    </script>
</x-layouts.app>
