@use('App\Models\Mapel')
@use('App\Models\PaketSoal')

@props(['paketTryout' => null, 'mapels' => [], 'paketSoals' => []])

@php
    // Satu baris per mapel, bukan lima slot tetap: jumlahnya bebas mengikuti
    // katalog mapel, dan admin memilih paket soal langsung pada mapelnya
    // sehingga tidak ada lagi peta slot yang harus disinkronkan lewat JS.
    //
    // Bawaan tiap baris: pilihan lama lebih dulu (old input menang atas data
    // tersimpan), lalu paket soal terbarunya milik mapel itu — daftar
    // `$paketSoals` sudah diurut menurun di controller, jadi yang pertama
    // sejajar dengan "paling baru".
    $isianTersimpan = collect($paketTryout?->daftarMapel)
        ->mapWithKeys(fn ($baris) => [(int) $baris->mapel_id => (int) $baris->paket_soal_id]);
    $isian = old('paket_soal', $isianTersimpan->all());

    $paketPerMapel = $paketSoals->groupBy(fn (PaketSoal $paketSoal) => (int) $paketSoal->mapel_id);

    $wajib = $mapels->filter(fn (Mapel $mapel) => $mapel->jenis === Mapel::JENIS_WAJIB);
    $pilihan = $mapels->filter(fn (Mapel $mapel) => $mapel->jenis !== Mapel::JENIS_WAJIB);

    // Mapel wajib dan pilihan dipisah lebih dulu; di dalam pilihan, per tingkat,
    // supaya admin melihat sasaran SMA dan SMK terpisah seperti isinya kelak
    // ditawarkan kepada peserta.
    $kelompok = [
        ['label' => 'Mapel wajib', 'mapels' => $wajib],
        ['label' => 'Mapel pilihan · SMA', 'mapels' => $pilihan->filter(fn (Mapel $mapel) => $mapel->tingkat === Mapel::TINGKAT_SMA)],
        ['label' => 'Mapel pilihan · SMK', 'mapels' => $pilihan->filter(fn (Mapel $mapel) => $mapel->tingkat === Mapel::TINGKAT_SMK)],
        ['label' => 'Mapel pilihan · Semua tingkat', 'mapels' => $pilihan->filter(fn (Mapel $mapel) => $mapel->tingkat === Mapel::TINGKAT_ALL)],
    ];
@endphp

<div class="space-y-4">
    <x-input label="Nama Paket" name="nama_paket" :value="$paketTryout?->nama_paket" required maxlength="255" />

    <x-textarea label="Deskripsi" name="deskripsi" :value="$paketTryout?->deskripsi" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="label">Tingkat</span>
            {{-- Sasaran paket tryout sudah menyatu, jadi tingkat bukan pilihan
                 yang bisa diubah. Nilai lama tetap terkirim apa adanya. --}}
            <input type="hidden" name="tingkat" id="tingkat-select"
                value="{{ old('tingkat', $paketTryout?->tingkat ?? \App\Models\PaketTryout::TINGKAT_SMK) }}">
            <p class="input cursor-default bg-slate-50 text-slate-600">SMA/SMK/Sederajat</p>
        </label>

        <label class="block">
            <span class="label">Batas Waktu (menit)</span>
            <input type="number" name="batas_waktu_menit" min="1" class="input"
                value="{{ old('batas_waktu_menit', $paketTryout?->batas_waktu_menit ?? 120) }}">
        </label>
    </div>

    @foreach ($kelompok as $grup)
        @if ($grup['mapels']->isNotEmpty())
            <section class="rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold text-ink">{{ $grup['label'] }}</h2>

                <div class="mt-3 space-y-3">
                    @foreach ($grup['mapels'] as $mapel)
                        @php
                            $opsi = $paketPerMapel->get($mapel->getKey()) ?? collect();
                            $terpilih = $isian[$mapel->getKey()] ?? $opsi->first()?->id;
                            $terpilih = filled($terpilih) ? (string) $terpilih : '';
                        @endphp

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:items-end">
                            <div>
                                <p class="label">{{ $mapel->nama }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $mapel->tingkat === 'all' ? 'Semua' : $mapel->tingkat }} ·
                                    {{ $mapel->is_pkk ? 'PKK' : ($mapel->jenis === Mapel::JENIS_PILIHAN_KEJURUAN ? 'Kejuruan' : ($mapel->jenis === Mapel::JENIS_PILIHAN_UMUM ? 'Umum' : 'Wajib')) }}
                                </p>
                            </div>

                            <div>
                                @if ($opsi->isEmpty())
                                    {{-- Baris ini tidak ikut disimpan: tanpa paket
                                         soal tidak ada yang bisa dipilih, dan
                                         mengharuskannya hanya mengunci form. --}}
                                    <p class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                        Belum ada paket soal yang bisa dipakai.
                                    </p>
                                @else
                                    <label class="block">
                                        <span class="label">Paket Soal</span>
                                        <select name="paket_soal[{{ $mapel->getKey() }}]" class="select" required>
                                            <option value="">— pilih paket soal —</option>

                                            @foreach ($opsi as $paketSoal)
                                                <option value="{{ $paketSoal->getKey() }}"
                                                    @selected($terpilih === (string) $paketSoal->getKey())>
                                                    {{ $paketSoal->nama_paket }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    <p class="hint">
        Seluruh mapel pada tingkat ini ikut masuk paket, dan peserta nanti memilih
        sendiri dua mapel pilihan yang ia kerjakan. SMK: minimal satu mapel pilihan
        berjenis pilihan_kejuruan atau berstatus PKK. Mapel ber-tingkat SMA juga
        dapat dipakai pada tryout SMK.
    </p>
</div>
