@props([
    'soal' => null,
    'kompetensiDasars' => [],
])

@php
    $opsiRows = old('opsi_jawaban', $soal?->opsi_jawaban?->toArray() ?? []);
    $pernyataanRows = old('pernyataan_kategori', $soal?->pernyataan_kategori?->toArray() ?? []);
    $tipeSelected = old('tipe_soal', $soal?->tipe_soal ?? 'pg');
    $daftarKategori = old('daftar_kategori', $soal?->daftar_kategori ?? []);

    if (empty($opsiRows)) {
        $opsiRows[] = [];
    }
    if (empty($pernyataanRows)) {
        $pernyataanRows[] = [];
    }

    $kdSelected = old('kompetensi_dasar_id', $soal?->kompetensi_dasar_id);
    $kdCurrent = $kdSelected ? $kompetensiDasars->firstWhere('id', $kdSelected) : null;
    $kdDisplay = $kdCurrent
        ? $kdCurrent->mapel->kode.' · '.$kdCurrent->kode_kompetensi.' — '.\Illuminate\Support\Str::limit($kdCurrent->deskripsi, 60)
        : '';
@endphp

<div class="space-y-4">
    <label class="block">
        <span class="label">Tipe Soal</span>
        <select name="tipe_soal" id="tipe_soal" class="select" required>
            @foreach (['pg' => 'Pilihan Ganda', 'pg_kompleks' => 'Pilihan Ganda Kompleks', 'pg_kategori' => 'Pilihan Ganda Kategori'] as $value => $label)
                <option value="{{ $value }}" @selected($tipeSelected === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="label">Kompetensi Dasar</span>
        <input type="text" id="kd-picker" list="kd-list" class="input" placeholder="Ketik kode atau nama KD…"
            value="{{ $kdDisplay }}" autocomplete="off">
        <input type="hidden" name="kompetensi_dasar_id" id="kompetensi_dasar_id"
            value="{{ old('kompetensi_dasar_id', $soal?->kompetensi_dasar_id) }}">
        <datalist id="kd-list">
            @foreach ($kompetensiDasars as $kd)
                <option value="{{ $kd->mapel->kode }} · {{ $kd->kode_kompetensi }} — {{ \Illuminate\Support\Str::limit($kd->deskripsi, 60) }}" data-id="{{ $kd->id }}"></option>
            @endforeach
        </datalist>
    </label>

    <x-textarea label="Pertanyaan" name="pertanyaan" value="{{ old('pertanyaan', $soal?->pertanyaan) }}" required />

    <x-textarea label="Pembahasan" name="pembahasan" value="{{ old('pembahasan', $soal?->pembahasan) }}" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="label">a (diskriminasi)</span>
            <input type="number" step="0.1" min="0.5" max="2.5" name="a_diskriminasi" id="a_diskriminasi"
                value="{{ old('a_diskriminasi', $soal?->a_diskriminasi ?? 1.0) }}" class="input">
        </label>
        <label class="block">
            <span class="label">b (kesulitan)</span>
            <input type="number" step="0.1" min="-3" max="3" name="b_kesulitan" id="b_kesulitan"
                value="{{ old('b_kesulitan', $soal?->b_kesulitan ?? 0.0) }}" class="input">
        </label>
        <label class="block">
            <span class="label">c (tebakan)</span>
            <input type="number" step="0.05" min="0" max="0.35" name="c_tebakan" id="c_tebakan"
                value="{{ old('c_tebakan', $soal?->c_tebakan ?? 0.25) }}" class="input">
        </label>
    </div>

    <section data-for-tipe="pg pg_kompleks" class="mt-6 border-t border-slate-200 pt-5">
        <h2 class="mb-3 text-base font-semibold text-ink">Opsi Jawaban</h2>
        <div class="opsi-list" id="opsi-list">
            @foreach ($opsiRows as $idx => $opsi)
                <div class="opsi-row">
                    <input type="hidden" name="opsi_jawaban[{{ $idx }}][urutan]" value="{{ $opsi['urutan'] ?? ($idx + 1) }}">
                    <input type="text" name="opsi_jawaban[{{ $idx }}][teks_opsi]" placeholder="Teks opsi"
                        value="{{ $opsi['teks_opsi'] ?? '' }}" class="input" required>
                    <label class="check">
                        <input type="checkbox" name="opsi_jawaban[{{ $idx }}][is_benar]" value="1" @checked(! empty($opsi['is_benar']))>
                        Benar
                    </label>
                    <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-row">Hapus</button>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-ghost" id="add-opsi">+ Tambah Opsi</button>
        <p class="hint">Minimal 5 opsi. PG: tepat 1 benar. PG Kompleks: minimal 2 benar.</p>
    </section>

    <section data-for-tipe="pg_kategori" class="mt-6 border-t border-slate-200 pt-5">
        <h2 class="mb-3 text-base font-semibold text-ink">Daftar Kategori</h2>
        <div class="kategori-list" id="kategori-list">
            @foreach (array_values($daftarKategori) as $idx => $kategori)
                <div class="kategori-row">
                    <input type="text" name="daftar_kategori[]" placeholder="Nama kategori (mis. Benar)"
                        value="{{ $kategori }}" class="input" required>
                    <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-row">Hapus</button>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-ghost" id="add-kategori">+ Tambah Kategori</button>

        <h2 class="mt-5 mb-3 text-base font-semibold text-ink">Pernyataan</h2>
        <div class="pernyataan-list" id="pernyataan-list">
            @foreach ($pernyataanRows as $idx => $pernyataan)
                <div class="pernyataan-row">
                    <input type="hidden" name="pernyataan_kategori[{{ $idx }}][urutan]" value="{{ $pernyataan['urutan'] ?? ($idx + 1) }}">
                    <input type="text" name="pernyataan_kategori[{{ $idx }}][teks_pernyataan]" placeholder="Teks pernyataan"
                        value="{{ $pernyataan['teks_pernyataan'] ?? '' }}" class="input" required>
                    <select name="pernyataan_kategori[{{ $idx }}][kategori_benar]" class="select" required>
                        <option value="">— pilih —</option>
                        @foreach ($daftarKategori as $kategori)
                            <option value="{{ $kategori }}" @selected(($pernyataan['kategori_benar'] ?? '') === $kategori)>{{ $kategori }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-row">Hapus</button>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-ghost" id="add-pernyataan">+ Tambah Pernyataan</button>
        <p class="hint">Minimal 2 pernyataan; kategori benar harus sesuai daftar kategori di atas.</p>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tipeInput = document.getElementById('tipe_soal');
        const form = document.getElementById('soal-form');
        const sections = form.querySelectorAll('section[data-for-tipe]');

        function syncForm() {
            const tipe = tipeInput.value;
            sections.forEach(function (section) {
                const allowed = (section.dataset.forTipe || '').split(' ').includes(tipe);
                section.style.display = allowed ? 'block' : 'none';
                section.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = !allowed;
                });
            });
        }
        tipeInput.addEventListener('change', syncForm);
        syncForm();

        // --- KD autocomplete sync ---
        var kdPicker = document.getElementById('kd-picker');
        var kdHidden = document.getElementById('kompetensi_dasar_id');
        var kdOptions = document.getElementById('kd-list').options;

        function syncKd() {
            for (var i = 0; i < kdOptions.length; i++) {
                if (kdOptions[i].value === kdPicker.value) {
                    kdHidden.value = kdOptions[i].dataset.id || '';
                    return;
                }
            }
            kdHidden.value = '';
        }
        kdPicker.addEventListener('input', syncKd);
        kdPicker.addEventListener('change', syncKd);

        // --- Row helpers ---
        function addRow(template, container, prefix, buildHtml) {
            var index = container.querySelectorAll('.opsi-row, .pernyataan-row, .kategori-row').length;
            var row = document.createElement('div');
            row.className = template + '-row';
            row.innerHTML = buildHtml(prefix + '[' + index + ']');
            container.appendChild(row);
        }

        document.getElementById('add-opsi').addEventListener('click', function () {
            addRow('opsi', document.getElementById('opsi-list'), 'opsi_jawaban', function (name) {
                return '<input type="hidden" name="' + name + '][urutan]">' +
                    '<input type="text" name="' + name + '][teks_opsi]" placeholder="Teks opsi" class="input" required>' +
                    '<label class="check"><input type="checkbox" name="' + name + '][is_benar]" value="1"> Benar</label>' +
                    '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-row">Hapus</button>';
            });
        });

        function buildKategoriSelect(name, daftarKategori) {
            var html = '<select name="' + name + '" class="select" required>' +
                '<option value="">— pilih —</option>';
            for (var i = 0; i < daftarKategori.length; i++) {
                html += '<option value="' + daftarKategori[i] + '">' + daftarKategori[i] + '</option>';
            }
            html += '</select>';
            return html;
        }

        document.getElementById('add-pernyataan').addEventListener('click', function () {
            var kategoriList = document.getElementById('kategori-list');
            var daftar = [];
            kategoriList.querySelectorAll('input[name="daftar_kategori[]"]').forEach(function (input) {
                if (input.value.trim()) daftar.push(input.value.trim());
            });
            addRow('pernyataan', document.getElementById('pernyataan-list'), 'pernyataan_kategori', function (name) {
                return '<input type="hidden" name="' + name + '][urutan]">' +
                    '<input type="text" name="' + name + '][teks_pernyataan]" placeholder="Teks pernyataan" class="input" required>' +
                    buildKategoriSelect(name + '[kategori_benar]', daftar) +
                    '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-row">Hapus</button>';
            });
        });

        document.getElementById('add-kategori').addEventListener('click', function () {
            var container = document.getElementById('kategori-list');
            var row = document.createElement('div');
            row.className = 'kategori-row';
            row.innerHTML = '<input type="text" name="daftar_kategori[]" placeholder="Nama kategori" class="input" required>' +
                '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-row">Hapus</button>';
            container.appendChild(row);
        });

        form.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-row')) {
                event.target.closest('.opsi-row, .pernyataan-row, .kategori-row').remove();
            }
        });
    });
</script>