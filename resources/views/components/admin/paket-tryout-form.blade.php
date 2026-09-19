@props(['paketTryout' => null, 'mapels' => [], 'paketSoals' => []])

@php
    $slots = [
        ['mapel' => 'mapel_wajib_1', 'paket' => 'paket_soal_wajib_1_id', 'label' => 'Wajib 1', 'paketSel' => 'paket-sel-0'],
        ['mapel' => 'mapel_wajib_2', 'paket' => 'paket_soal_wajib_2_id', 'label' => 'Wajib 2', 'paketSel' => 'paket-sel-1'],
        ['mapel' => 'mapel_wajib_3', 'paket' => 'paket_soal_wajib_3_id', 'label' => 'Wajib 3', 'paketSel' => 'paket-sel-2'],
        ['mapel' => 'mapel_pilihan_1', 'paket' => 'paket_soal_pilihan_1_id', 'label' => 'Pilihan 1', 'paketSel' => 'paket-sel-3'],
        ['mapel' => 'mapel_pilihan_2', 'paket' => 'paket_soal_pilihan_2_id', 'label' => 'Pilihan 2', 'paketSel' => 'paket-sel-4'],
    ];
@endphp

<div class="space-y-4">
    <x-input label="Nama Paket" name="nama_paket" value="{{ old('nama_paket', $paketTryout?->nama_paket) }}" required maxlength="255" />

    <x-textarea label="Deskripsi" name="deskripsi" value="{{ old('deskripsi', $paketTryout?->deskripsi) }}" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-select label="Tingkat" name="tingkat" id="tingkat-select" required
            :options="['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'SMK' => 'SMK']"
            :value="old('tingkat', $paketTryout?->tingkat ?? 'SMK')" />

        <label class="block">
            <span class="label">Batas Waktu (menit)</span>
            <input type="number" name="batas_waktu_menit" min="1" class="input"
                value="{{ old('batas_waktu_menit', $paketTryout?->batas_waktu_menit ?? 120) }}">
        </label>
    </div>

    @foreach ($slots as $slot)
        <div class="rounded-lg border border-slate-200 p-4">
            <h2 class="mb-3 text-sm font-semibold text-ink">{{ $slot['label'] }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="label">Mapel</span>
                    <select name="{{ $slot['mapel'] }}" class="select mapel-slot" data-paket-sel="{{ $slot['paketSel'] }}" required>
                        @foreach ($mapels as $mapel)
                            <option value="{{ $mapel->id }}" data-tingkat="{{ $mapel->tingkat }}"
                                @selected((string) old($slot['mapel'], $paketTryout?->getAttribute($slot['mapel'])) === (string) $mapel->id)>
                                {{ $mapel->nama }} · {{ $mapel->tingkat === 'all' ? 'Semua' : $mapel->tingkat }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="label">Paket Soal</span>
                    <select name="{{ $slot['paket'] }}" id="{{ $slot['paketSel'] }}" class="select paket-sel" required>
                        @foreach ($paketSoals as $paketSoal)
                            <option value="{{ $paketSoal->id }}" data-mapel="{{ $paketSoal->mapel_id }}" data-tingkat="{{ $paketSoal->mapel?->tingkat }}"
                                @selected((string) old($slot['paket'], $paketTryout?->getAttribute($slot['paket'])) === (string) $paketSoal->id)>
                                {{ $paketSoal->nama_paket }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    @endforeach

    <p class="hint">SMK: minimal salah satu mapel pilihan harus berjenis pilihan_kejuruan atau berstatus PKK. Paket soal pada tiap slot otomatis difilter mengikuti mapel yang dipilih. Mapel ber-tingkat SMA juga dapat dipakai pada tryout SMK.</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tingkatSelect = document.getElementById('tingkat-select');

        function cocokTingkat(optionTingkat) {
            const tingkat = tingkatSelect.value;
            return optionTingkat === tingkat || optionTingkat === 'all'
                || (tingkat === 'SMK' && optionTingkat === 'SMA');
        }

        function filterOpsiDenganTingkat() {
            document.querySelectorAll('.mapel-slot option, .paket-sel option').forEach(function (option) {
                option.hidden = !cocokTingkat(option.dataset.tingkat);
            });
        }

        function filterPaketPerMapel(mapelSelect) {
            const mapelId = mapelSelect.value;
            document.getElementById(mapelSelect.dataset.paketSel).querySelectorAll('option').forEach(function (option) {
                const cocok = cocokTingkat(option.dataset.tingkat) && (mapelId === '' || option.dataset.mapel === mapelId);
                option.hidden = !cocok;
            });
        }

        document.querySelectorAll('.mapel-slot').forEach(function (select) {
            select.addEventListener('change', function () { filterPaketPerMapel(select); });
        });

        tingkatSelect.addEventListener('change', function () {
            filterOpsiDenganTingkat();
            document.querySelectorAll('.mapel-slot').forEach(filterPaketPerMapel);
        });

        filterOpsiDenganTingkat();
        document.querySelectorAll('.mapel-slot').forEach(filterPaketPerMapel);
    });
</script>