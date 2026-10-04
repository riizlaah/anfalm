<x-layouts.app title="Latihan">
    <h1 class="page-title">Latihan</h1>

    <x-alert />

    @if ($berjalan->isNotEmpty())
        <section class="card mt-5 p-5">
            <h2 class="card-title">Latihan berlangsung</h2>

            <ul class="mt-3 space-y-2">
                @foreach ($berjalan as $latihan)
                    <li class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm text-slate-700">
                            {{ $latihan->mapel?->nama ?? 'Latihan' }} · {{ $latihan->jumlah_soal }} soal
                            @if ($latihan->batas_waktu_menit)
                                · {{ $latihan->batas_waktu_menit }} menit
                            @else
                                · stopwatch
                            @endif
                        </span>

                        <a href="{{ route('latihan.kerja', $latihan) }}" class="btn btn-ghost">Lanjutkan</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ route('latihan.mulai') }}" class="card mt-5 space-y-5 p-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            {{-- `:value` diisi dari `?mapel=` yang dibawa tautan dashboard;
                 `old()` tetap menang bila validasi `mulai()` gagal, sehingga
                 pilihan peserta tidak hilang saat form dikirim ulang. JS di
                 bawah memanggil `isiKd()` saat muat, jadi daftar kompetensi
                 dasar ikut terisi untuk mapel yang sudah terpilih ini. --}}
            <x-select label="Mapel" name="mapel_id" empty-option="Pilih mapel…"
                :value="$mapelTerpilih"
                :options="$mapels->mapWithKeys(fn ($mapel): array => [$mapel->getKey() => $mapel->nama])" />

            <x-select label="Kompetensi dasar (opsional)" name="kompetensi_dasar_id"
                empty-option="Semua kompetensi dasar" :options="[]" />
        </div>

        <div>
            <x-input label="Jumlah soal (1–30)" name="jumlah_soal" type="number" min="1" max="30"
                :value="old('jumlah_soal', 5)" required />

            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($jumlahPreset as $preset)
                    <button type="button" class="btn btn-ghost" data-jumlah="{{ $preset }}">{{ $preset }}</button>
                @endforeach
            </div>
        </div>

        <div>
            <span class="label">Timer</span>

            <div class="mt-2 flex flex-wrap items-center gap-4">
                <label class="check">
                    <input type="radio" name="timer" value="stopwatch" @checked(old('timer', 'stopwatch') === 'stopwatch')>
                    <span>Stopwatch — mencatat durasi tanpa batas</span>
                </label>

                <label class="check">
                    <input type="radio" name="timer" value="countdown" @checked(old('timer') === 'countdown')>
                    <span>Countdown — berhenti saat waktu habis</span>
                </label>
            </div>
        </div>

        <div id="blok-batas" class="hidden">
            <x-input label="Batas waktu (menit)" name="batas_waktu_menit" type="number" min="1" max="600"
                :value="old('batas_waktu_menit')" />
        </div>

        <x-errors />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Mulai Latihan</button>
        </div>
    </form>

    <script type="application/json" id="kd-peta">@json($kdPeta)</script>

    <script>
        (function () {
            const peta = JSON.parse(document.getElementById('kd-peta').textContent);
            const pilihMapel = document.querySelector('select[name="mapel_id"]');
            const pilihKd = document.querySelector('select[name="kompetensi_dasar_id"]');
            const pilihJumlah = document.querySelector('input[name="jumlah_soal"]');

            // Daftar KD hanya relevan untuk mapel yang sedang dipilih, jadi
            // dipasang dari peta yang sudah disertakan tanpa permintaan tambahan.
            function isiKd() {
                const terpilih = pilihKd.value;
                pilihKd.innerHTML = '';

                pilihKd.append(new Option('Semua kompetensi dasar', ''));

                (peta[pilihMapel.value] || []).forEach((kd) => {
                    pilihKd.append(new Option(kd.label, kd.id));
                });

                if (pilihKd.querySelector('option[value="' + CSS.escape(terpilih) + '"]')) {
                    pilihKd.value = terpilih;
                }
            }

            pilihMapel.addEventListener('change', isiKd);
            isiKd();

            document.querySelectorAll('[data-jumlah]').forEach((tombol) => {
                tombol.addEventListener('click', () => {
                    pilihJumlah.value = tombol.dataset.jumlah;
                });
            });

            const blokBatas = document.getElementById('blok-batas');

            function isiTimer() {
                blokBatas.classList.toggle(
                    'hidden',
                    document.querySelector('input[name="timer"]:checked').value !== 'countdown'
                );
            }

            document.querySelectorAll('input[name="timer"]').forEach((radio) => {
                radio.addEventListener('change', isiTimer);
            });

            isiTimer();
        })();
    </script>
</x-layouts.app>
