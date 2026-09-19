<x-layouts.app title="Generate Paket Soal dari AI">
    <h1 class="page-title">Generate Paket Soal dari AI</h1>

    <x-alert />
    <x-errors />

    <form id="generate-form" method="POST" action="{{ route('admin.paket-soal.generate.store') }}" class="card mt-5 max-w-4xl space-y-4 p-6">
        @csrf

        <label class="block">
            <span class="label">Mapel</span>
            <select name="mapel_id" id="mapel-select" class="select" required>
                <option value="" @selected(old('mapel_id', $generateInput['mapel_id'] ?? '') === '')>— pilih mapel —</option>
                @foreach ($mapels as $mapel)
                    <option value="{{ $mapel->id }}"
                        data-kd-count="{{ $mapel->kompetensiDasar->count() }}"
                        @selected((string) old('mapel_id', $generateInput['mapel_id'] ?? '') === (string) $mapel->id)>
                        {{ $mapel->nama }}
                    </option>
                @endforeach
            </select>
        </label>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="label mb-0">Kompetensi Dasar</span>
                <label class="flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-600">
                    <input type="checkbox" id="kd-select-all" class="h-4 w-4 rounded border-slate-300 accent-ink">
                    Pilih semua KD
                </label>
            </div>
            <p class="hint" id="kd-hint">Pilih mapel terlebih dahulu untuk menampilkan daftar KD.</p>
            <div class="kd-list mt-2 grid grid-cols-1 gap-2 md:grid-cols-2" id="kd-list">
                @foreach ($mapels as $mapel)
                    @foreach ($mapel->kompetensiDasar as $kd)
                        <label class="kd-item rounded-md border border-slate-200 px-3 py-2 text-sm" data-mapel-id="{{ $mapel->id }}">
                            <input type="checkbox" name="kompetensi_dasar_ids[]" value="{{ $kd->id }}" class="h-4 w-4 rounded border-slate-300 accent-ink"
                                @checked(in_array($kd->id, old('kompetensi_dasar_ids', $generateInput['kompetensi_dasar_ids'] ?? []), true))>
                            <span class="ml-2 font-medium">{{ $kd->kode_kompetensi }}</span>
                            <span class="ml-1 text-slate-500">{{ \Illuminate\Support\Str::limit($kd->deskripsi, 70) }}</span>
                        </label>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="label">Jumlah Soal (maksimum 30)</span>
                <input type="number" name="jumlah_soal" min="1" max="30" class="input" required
                    value="{{ old('jumlah_soal', $generateInput['jumlah_soal'] ?? 15) }}">
            </label>

            <label class="block">
                <span class="label">Tingkat Kesulitan</span>
                <select name="tingkat_kesulitan" class="select" required>
                    @foreach (['mudah' => 'Mudah', 'sedang' => 'Sedang', 'sulit' => 'Sulit', 'campuran' => 'Campuran'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('tingkat_kesulitan', $generateInput['tingkat_kesulitan'] ?? 'campuran') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="block">
            <span class="label">Referensi Tambahan (opsional)</span>
            <textarea name="referensi" rows="4" class="textarea"
                placeholder="Tempel teks referensi materi (bab, halaman, rumus) yang ingin dijadikan acuan…">{{ old('referensi', $generateInput['referensi'] ?? '') }}</textarea>
        </label>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="hint">Soal dibuat merata di seluruh KD yang dipilih, dikerjakan bertahap (±6 soal per bagian). Tunggu prosesnya hingga selesai, lalu Anda kurasi sebelum disimpan.</p>
            <button type="submit" id="generate-submit" class="btn btn-primary">Generate Paket</button>
        </div>
    </form>

    <div id="generate-progress" class="fixed inset-0 z-50 hidden"
        data-kurasi-url="{{ route('admin.paket-soal.kurasi') }}">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div class="relative z-10 mx-auto mt-32 max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <span class="inline-block h-6 w-6 flex-none animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"></span>
                <p id="progress-text" class="text-sm font-medium text-slate-700">Menyiapkan generate…</p>
            </div>
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div id="progress-bar" class="h-full rounded-full bg-indigo-600 transition-all duration-500" style="width:0%"></div>
            </div>
            <div id="generate-fail" class="mt-4 hidden rounded-md border border-red-200 bg-red-50 p-4">
                <p id="generate-error" class="text-sm text-red-700"></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" id="retry-part" class="btn btn-primary">Coba Lagi</button>
                    <button type="button" id="cancel-generate" class="btn btn-ghost">Batalkan</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapelSelect = document.getElementById('mapel-select');
            const kdList = document.getElementById('kd-list');
            const kdHint = document.getElementById('kd-hint');
            const selectAll = document.getElementById('kd-select-all');

            function kdItems() {
                return Array.from(kdList.querySelectorAll('.kd-item'));
            }

            function visibleItems() {
                return kdItems().filter(function (item) { return item.style.display !== 'none'; });
            }

            function checkboxes() {
                return visibleItems().map(function (item) { return item.querySelector('input[type="checkbox"]'); });
            }

            function filterKd() {
                const mapelId = mapelSelect.value;
                const tampil = [];

                kdItems().forEach(function (item) {
                    const cocok = mapelId !== '' && item.dataset.mapelId === mapelId;
                    item.style.display = cocok ? '' : 'none';
                    if (cocok) tampil.push(item);
                });

                kdHint.textContent = mapelId === ''
                    ? 'Pilih mapel terlebih dahulu untuk menampilkan daftar KD.'
                    : 'Pilih satu atau beberapa KD sebagai acuan pembuatan soal ('.concat(tampil.length).concat(' KD tersedia).');
            }

            function updateSelectAllState() {
                const boxes = checkboxes();
                if (boxes.length === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;

                    return;
                }

                const checked = boxes.filter(function (box) { return box.checked; }).length;
                selectAll.checked = checked === boxes.length;
                selectAll.indeterminate = checked > 0 && checked < boxes.length;
            }

            mapelSelect.addEventListener('change', function () {
                const mapelId = mapelSelect.value;

                kdItems().forEach(function (item) {
                    const box = item.querySelector('input[type="checkbox"]');
                    box.checked = mapelId !== '' && item.dataset.mapelId === mapelId;
                });

                filterKd();
                updateSelectAllState();
            });

            selectAll.addEventListener('change', function () {
                checkboxes().forEach(function (box) { box.checked = selectAll.checked; });
                updateSelectAllState();
            });

            kdList.addEventListener('change', function (event) {
                if (event.target.matches('input[type="checkbox"]')) {
                    updateSelectAllState();
                }
            });

            filterKd();
            updateSelectAllState();

            const form = document.getElementById('generate-form');
            const progress = document.getElementById('generate-progress');
            const progressText = document.getElementById('progress-text');
            const progressBar = document.getElementById('progress-bar');
            const failBox = document.getElementById('generate-fail');
            const errorText = document.getElementById('generate-error');
            const retryBtn = document.getElementById('retry-part');
            const cancelBtn = document.getElementById('cancel-generate');

            let partBerjalan = 1;
            let partTotal = null;
            let akumulasi = 0;
            let payloadPart = null;

            function muatPayloadUtuh() {
                return {
                    mapel_id: form.elements.mapel_id.value,
                    kompetensi_dasar_ids: Array.from(form.querySelectorAll('input[name="kompetensi_dasar_ids[]"]:checked')).map(function (c) { return c.value; }),
                    jumlah_soal: form.elements.jumlah_soal.value,
                    tingkat_kesulitan: form.elements.tingkat_kesulitan.value,
                    referensi: form.elements.referensi.value,
                };
            }

            function pesanStatus(res, data) {
                if (data && data.error) return data.error;
                if (data && data.message) return data.message;

                return 'Gagal menghubungi layanan (kode status '.concat(res.status).concat(').');
            }

            function buatRunId() {
                return Date.now().toString(36).concat('-').concat(Math.random().toString(36).slice(2, 10));
            }

            async function jalankanPart(part) {
                partBerjalan = part;
                payloadPart.part = part;
                failBox.classList.add('hidden');

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify(payloadPart),
                    });

                    const json = await res.json().catch(function () { return null; });

                    if (!res.ok || !json || json.ok !== true) {
                        tampilKegagalan(pesanStatus(res, json));

                        return;
                    }

                    if (partTotal === null) partTotal = json.part_total;
                    akumulasi = json.jumlah_akumulasi;

                    updateProgress(labelPart(json.part));

                    if (json.selesai) {
                        window.location.href = progress.dataset.kurasiUrl;

                        return;
                    }

                    await jalankanPart(json.part + 1);
                } catch (e) {
                    tampilKegagalan('Gagal terhubung ke server. Periksa koneksi, lalu coba lagi.');
                }
            }

            function labelPart(part) {
                const target = form.elements.jumlah_soal.value;

                return 'Menghasilkan part '.concat(part).concat('/').concat(partTotal)
                    .concat(' — ').concat(akumulasi).concat('/').concat(target).concat(' soal…');
            }

            function updateProgress(label) {
                progressText.textContent = label;
                const porsi = partTotal > 0 ? ((partBerjalan - 1) / partTotal) * 100 : 0;
                progressBar.style.width = porsi + '%';
            }

            function tampilKegagalan(pesan) {
                errorText.textContent = 'Bagian '.concat(partBerjalan).concat('/').concat(partTotal ?? '?')
                    .concat(' gagal: ').concat(pesan);
                failBox.classList.remove('hidden');
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (!form.reportValidity()) return;
                if (muatPayloadUtuh().kompetensi_dasar_ids.length === 0) {
                    failBox.classList.remove('hidden');
                    errorText.textContent = 'Pilih minimal satu Kompetensi Dasar terlebih dahulu.';

                    return;
                }

                payloadPart = muatPayloadUtuh();
                payloadPart.run = buatRunId();
                partBerjalan = 1;
                partTotal = null;
                akumulasi = 0;
                updateProgress('Menyiapkan…');
                progress.classList.remove('hidden');
                jalankanPart(1);
            });

            retryBtn.addEventListener('click', function () {
                jalankanPart(partBerjalan);
            });

            cancelBtn.addEventListener('click', function () {
                progress.classList.add('hidden');
                failBox.classList.add('hidden');
                partBerjalan = 1;
                partTotal = null;
            });
        });
    </script>
</x-layouts.app>