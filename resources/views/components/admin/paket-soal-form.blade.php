@props(['paket' => null, 'mapels' => [], 'soals' => []])

@php
    $mapelId = old('mapel_id', $paket?->mapel_id);
    $selectedSoalIds = old('soal_ids', $paket?->soal?->pluck('id')->all() ?? []);
    $soalByMapel = $soals->groupBy(fn ($soal) => $soal->kompetensiDasar->mapel_id);
@endphp

<div class="space-y-4">
    <x-input label="Nama Paket" name="nama_paket" value="{{ old('nama_paket', $paket?->nama_paket) }}" required maxlength="255" />

    <x-textarea label="Deskripsi" name="deskripsi" value="{{ old('deskripsi', $paket?->deskripsi) }}" />

    <x-select label="Mapel" name="mapel_id" id="mapel-picker" required :value="$mapelId"
        :options="$mapels->pluck('nama', 'id')->all()" />

    <label class="block">
        <span class="label">Pilih Soal</span>
        @if ($soals->isEmpty())
            <p class="hint">Belum ada soal. Buat soal terlebih dahulu di menu Soal.</p>
        @else
            <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                @foreach ($soalByMapel as $soalMapelId => $soalList)
                    <div class="soal-group" data-mapel="{{ $soalMapelId }}">
                        <p class="mb-2 text-sm font-semibold text-ink">{{ $soalList->first()->kompetensiDasar?->mapel?->nama }}</p>
                        @foreach ($soalList as $soal)
                            <label class="check mb-2">
                                <input type="checkbox" name="soal_ids[]" value="{{ $soal->id }}" class="soal-check"
                                    @checked(in_array($soal->id, $selectedSoalIds))>
                                <span class="truncate">{{ \Illuminate\Support\Str::limit($soal->pertanyaan, 110) }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <p class="hint">Soal ditampilkan sesuai mapel yang dipilih. Minimal 1 soal.</p>
        @endif
    </label>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapelPicker = document.getElementById('mapel-picker');

        function filterSoal() {
            const mapelId = mapelPicker.value;
            document.querySelectorAll('.soal-group').forEach(function (group) {
                group.style.display = group.dataset.mapel === mapelId ? '' : 'none';
            });
        }

        mapelPicker.addEventListener('change', filterSoal);
        filterSoal();
    });
</script>