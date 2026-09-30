<x-layouts.app title="Kurasi Paket Soal dari AI">
    <h1 class="page-title">Kurasi Paket Soal dari AI</h1>

    <x-alert />
    <x-errors />

    @php
        $kurasiSoals = old('daftar_soal', $draft['daftar_soal'] ?? []);
        $namaPaket = old('nama_paket', $draft['nama_paket'] ?? '');
        $deskripsi = old('deskripsi', $draft['deskripsi'] ?? '');

        $soalKdMap = $kompetensiDasars->keyBy('kode_kompetensi');

        // Baris yang belum mencapai jumlah minimum dilengkapi agar kurasi
        // tetap bisa diisi tanpa harus menambah baris satu per satu.
        $soalViews = [];
        foreach ($kurasiSoals as $index => $s) {
            $kdKode = $s['kompetensi_dasar_kode'] ?? null;
            $kdId = (int) ($s['kompetensi_dasar_id'] ?? ($kdKode !== null ? ($soalKdMap[$kdKode]->id ?? 0) : 0));
            $opsiRows = array_values($s['opsi_jawaban'] ?? []);
            $pernyataanRows = array_values($s['pernyataan_kategori'] ?? []);
            $soalViews[] = [
                'index' => $index,
                'sementara' => $s['id_soal_sementara'] ?? '',
                'hapus' => ! empty($s['dihapus']),
                'tipe' => $s['tipe_soal'] ?? 'pg',
                'kd_id' => $kdId,
                'pertanyaan' => $s['pertanyaan'] ?? '',
                'pembahasan' => $s['pembahasan'] ?? '',
                'gambar_url' => $s['gambar_url'] ?? '',
                'a' => $s['a_diskriminasi'] ?? '',
                'b' => $s['b_kesulitan'] ?? '',
                'c' => $s['c_tebakan'] ?? '',
                'opsi_rows' => array_pad($opsiRows, max(count($opsiRows), 5), []),
                'pernyataan_rows' => array_pad($pernyataanRows, max(count($pernyataanRows), 3), []),
                'kategori_list' => array_values(array_filter((array) ($s['daftar_kategori'] ?? ['Benar', 'Salah']))),
            ];
        }
    @endphp

    @if (! empty($draft['soal_dibuang']))
        <div class="card mt-5 border-l-4 border-l-gold bg-gold/5 p-6">
            <h2 class="text-base font-semibold text-ink">Soal yang dibuang otomatis</h2>
            <ul class="mt-2 list-inside list-disc text-sm text-slate-600">
                @foreach ($draft['soal_dibuang'] as $dibuang)
                    <li>{{ $dibuang['id'] }} — {{ $dibuang['alasan'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.paket-soal.simpan') }}" id="kurasi-form">
        @csrf

        {{-- Seluruh daftar soal dikirim sebagai satu field JSON agar tidak tergantung
             max_input_vars PHP (default 1000); form biasa membutuhkan ±1.220 field untuk 30 soal. --}}
        <input type="hidden" name="daftar_soal_json" id="daftar-soal-json">

        <div class="card mt-5 grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            <label class="block">
                <span class="label">Nama Paket</span>
                <input type="text" name="nama_paket" class="input" required value="{{ $namaPaket }}" placeholder="cth. Tryout TKA Matematika 2025">
            </label>
            <label class="block">
                <span class="label">Deskripsi (opsional)</span>
                <input type="text" name="deskripsi" class="input" value="{{ $deskripsi }}">
            </label>
        </div>

        @foreach ($soalViews as $sv)
            @php
                $i = $sv['index'];
                $opsiRows = $sv['opsi_rows'];
                $pernyataanRows = $sv['pernyataan_rows'];
                $kategoriList = $sv['kategori_list'];
            @endphp

            <div class="card soal-card mt-5 p-6 @if ($sv['hapus']) opacity-50 @endif" data-index="{{ $i }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                    <h2 class="text-base font-semibold text-ink">
                        Soal #{{ $loop->iteration }}@if ($sv['sementara'])
                            <span class="ml-1 text-xs font-normal text-slate-400">{{ $sv['sementara'] }}</span>
                        @endif
                    </h2>

                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-600">
                        <input type="checkbox" name="daftar_soal[{{ $i }}][dihapus]" value="1" class="dihapus-check h-4 w-4 rounded border-slate-300 accent-ink"
                            @checked($sv['hapus'])>
                        Hapus soal ini
                    </label>
                </div>

                <input type="hidden" name="daftar_soal[{{ $i }}][id_soal_sementara]" value="{{ $sv['sementara'] }}">

                <div class="card-body mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <label class="block">
                        <span class="label">Tipe Soal</span>
                        <select name="daftar_soal[{{ $i }}][tipe_soal]" class="select kurasi-tipe" required>
                            @foreach (['pg' => 'Pilihan Ganda', 'pg_kompleks' => 'Pilihan Ganda Kompleks', 'pg_kategori' => 'Pilihan Ganda Kategori'] as $value => $label)
                                <option value="{{ $value }}" @selected($sv['tipe'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="label">Kompetensi Dasar</span>
                        <select name="daftar_soal[{{ $i }}][kompetensi_dasar_id]" class="select" required>
                            <option value="" @selected($sv['kd_id'] === 0)>— pilih KD —</option>
                            @foreach ($kompetensiDasars as $kd)
                                <option value="{{ $kd->id }}"
                                    data-kode="{{ $kd->kode_kompetensi }}"
                                    @selected((int) $sv['kd_id'] === (int) $kd->id)>
                                    {{ $kd->kode_kompetensi }} — {{ \Illuminate\Support\Str::limit($kd->deskripsi, 60) }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block sm:col-span-3">
                        <span class="label">Pertanyaan</span>
                        <textarea name="daftar_soal[{{ $i }}][pertanyaan]" rows="3" class="textarea" required>{{ $sv['pertanyaan'] }}</textarea>
                    </label>

                    <label class="block sm:col-span-3">
                        <span class="label">Pembahasan</span>
                        <textarea name="daftar_soal[{{ $i }}][pembahasan]" rows="3" class="textarea">{{ $sv['pembahasan'] }}</textarea>
                    </label>

                    <label class="block sm:col-span-3">
                        <span class="label">URL Gambar <span class="font-normal text-slate-400">(opsional)</span></span>
                        <input type="url" name="daftar_soal[{{ $i }}][gambar_url]" class="input"
                            value="{{ $sv['gambar_url'] }}" placeholder="https://…">
                    </label>

                    <div class="sm:col-span-3">
                        <span class="label">Parameter IRT Soal <span class="font-normal text-slate-400">(opsional)</span></span>
                        <div class="grid grid-cols-3 gap-3 sm:max-w-md">
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">a — diskriminasi <span class="text-slate-400">(0.5–2.5)</span></span>
                                <input type="number" step="0.01" min="0.5" max="2.5" name="daftar_soal[{{ $i }}][a_diskriminasi]" class="input w-full px-2 py-1.5 text-sm" value="{{ $sv['a'] }}">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">b — kesulitan <span class="text-slate-400">(−3–3)</span></span>
                                <input type="number" step="0.01" min="-3" max="3" name="daftar_soal[{{ $i }}][b_kesulitan]" class="input w-full px-2 py-1.5 text-sm" value="{{ $sv['b'] }}">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">c — tebakan <span class="text-slate-400">(0–0.35)</span></span>
                                <input type="number" step="0.01" min="0" max="0.35" name="daftar_soal[{{ $i }}][c_tebakan]" class="input w-full px-2 py-1.5 text-sm" value="{{ $sv['c'] }}">
                            </label>
                        </div>
                    </div>
                </div>

                <section class="kurasi-opsi-section mt-5 border-t border-slate-200 pt-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-ink">
                            Opsi Jawaban
                            <span class="font-normal text-slate-400">(minimal 5, maksimal 8)</span>
                        </h3>
                        <button type="button" class="btn btn-ghost px-2.5 py-1 text-xs add-opsi-row">+ Tambah Opsi</button>
                    </div>

                    <div class="opsi-list">
                        @foreach ($opsiRows as $oIdx => $opsi)
                            <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3" data-opsi-row>
                                <input type="hidden" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][urutan]" value="{{ $opsi['urutan'] ?? $oIdx + 1 }}">

                                <div class="grid grid-cols-1 items-start gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <textarea name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][teks_opsi]" rows="2" class="textarea" required>{{ $opsi['teks_opsi'] ?? '' }}</textarea>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <input type="hidden" class="is-benar-hidden"
                                            name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][is_benar]"
                                            value="{{ ! empty($opsi['is_benar']) ? '1' : '0' }}">

                                        <label class="benar-control flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-700" data-opsi-for="pg">
                                            <input type="radio" class="benar-radio accent-ink" name="benar_pilih_{{ $i }}"
                                                value="{{ $oIdx }}" @checked(! empty($opsi['is_benar']))>
                                            Benar
                                        </label>

                                        <label class="benar-control flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-700" data-opsi-for="pg_kompleks">
                                            <input type="checkbox" class="benar-check h-4 w-4 rounded border-slate-300 accent-ink"
                                                @checked(! empty($opsi['is_benar']))>
                                            Benar
                                        </label>

                                        <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-opsi-row">Hapus</button>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2 sm:max-w-64">
                                    @foreach (['a_diskriminasi' => ['a', '0.5–2.5'], 'b_kesulitan' => ['b', '−3–3'], 'c_tebakan' => ['c', '0–0.35']] as $irtName => $irt)
                                        <label class="block">
                                            <span class="mb-1 block text-[11px] font-medium text-slate-500">{{ $irt[0] }} <span class="text-slate-400">{{ $irt[1] }}</span></span>
                                            <input type="number" step="0.01"
                                                name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][{{ $irtName }}]"
                                                class="input w-full px-2 py-1.5 text-sm" value="{{ $opsi[$irtName] ?? '' }}">
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="kurasi-kategori-section mt-5 border-t border-slate-200 pt-4">
                    <h3 class="mb-2 text-sm font-semibold text-ink">Daftar Kategori</h3>
                    <div class="kategori-list mb-5 flex flex-wrap gap-2">
                        @foreach ($kategoriList as $kategori)
                            <input type="text" name="daftar_soal[{{ $i }}][daftar_kategori][]" value="{{ $kategori }}"
                                class="input kurasi-kategori-input w-40" required>
                        @endforeach
                        <button type="button" class="btn btn-ghost px-2.5 py-1 text-xs add-kategori-row">+ Kategori</button>
                    </div>

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-ink">
                            Pernyataan
                            <span class="font-normal text-slate-400">(minimal 3, maksimal 5)</span>
                        </h3>
                        <button type="button" class="btn btn-ghost px-2.5 py-1 text-xs add-pernyataan-row">+ Tambah Pernyataan</button>
                    </div>

                    <div class="pernyataan-list">
                        @foreach ($pernyataanRows as $pIdx => $pernyataan)
                            <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3" data-pernyataan-row>
                                <input type="hidden" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][urutan]" value="{{ $pernyataan['urutan'] ?? $pIdx + 1 }}">

                                <div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_auto]">
                                    <input type="text" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][teks_pernyataan]" class="input" required
                                        value="{{ $pernyataan['teks_pernyataan'] ?? '' }}" placeholder="Teks pernyataan">
                                    <select name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][kategori_benar]" class="select kurasi-kategori-select" required>
                                        <option value="">— pilih —</option>
                                        @foreach ($kategoriList as $kategori)
                                            <option value="{{ $kategori }}" @selected(($pernyataan['kategori_benar'] ?? '') === $kategori)>{{ $kategori }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-pernyataan-row">Hapus</button>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2 sm:max-w-64">
                                    @foreach (['a_diskriminasi' => ['a', '0.5–2.5'], 'b_kesulitan' => ['b', '−3–3'], 'c_tebakan' => ['c', '0–0.35']] as $irtName => $irt)
                                        <label class="block">
                                            <span class="mb-1 block text-[11px] font-medium text-slate-500">{{ $irt[0] }} <span class="text-slate-400">{{ $irt[1] }}</span></span>
                                            <input type="number" step="0.01"
                                                name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][{{ $irtName }}]"
                                                class="input w-full px-2 py-1.5 text-sm" value="{{ $pernyataan[$irtName] ?? '' }}">
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @endforeach

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <p class="hint">Pembahasan dapat berisi LaTeX \( \frac{1}{2} \). Parameter IRT (a, b, c) dapat disesuaikan di sini.</p>
            <div class="flex gap-2">
                <a href="{{ route('admin.paket-soal.generate') }}" class="btn btn-ghost">Generate Ulang</a>
                <button type="submit" class="btn btn-primary">Simpan Paket</button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('kurasi-form');
            const jsonInput = document.getElementById('daftar-soal-json');

            const MIN_OPSI = 5;
            const MAX_OPSI = 8;
            const BATAS_HAPUS_PERNYATAAN = 3;
            const MAX_PERNYATAAN = 5;

            function tampilkanKesalahan(pesan) {
                let box = document.getElementById('kurasi-js-error');
                if (!box) {
                    box = document.createElement('div');
                    box.id = 'kurasi-js-error';
                    box.className = 'mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700';
                    box.innerHTML = '<p class="font-semibold">Periksa kembali isian berikut:</p><ul class="mt-1 list-inside list-disc space-y-0.5"></ul>';
                    form.parentNode.insertBefore(box, form);
                }
                const list = box.querySelector('ul');
                list.innerHTML = '<li></li>';
                list.firstElementChild.textContent = pesan;
                box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function toggleSections(card) {
                const tipe = card.querySelector('.kurasi-tipe').value;
                card.querySelectorAll('.kurasi-opsi-section, .kurasi-kategori-section').forEach(function (section) {
                    const pakaiOpsi = section.classList.contains('kurasi-opsi-section');
                    const show = pakaiOpsi ? (tipe === 'pg' || tipe === 'pg_kompleks') : (tipe === 'pg_kategori');
                    section.style.display = show ? '' : 'none';
                    // Nonaktifkan input section tersembunyi (bukan sekadar display:none)
                    // agar tidak ikut terkirim — sama seperti form soal manual.
                    section.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                        el.disabled = !show;
                    });
                });
            }

            function refreshBenarControls(card) {
                const tipe = card.querySelector('.kurasi-tipe').value;
                card.querySelectorAll('[data-opsi-row]').forEach(function (row) {
                    const hidden = row.querySelector('.is-benar-hidden');
                    const isBenar = hidden !== null && hidden.value === '1';
                    row.querySelectorAll('.benar-control').forEach(function (control) {
                        const aktif = control.dataset.opsiFor === tipe;
                        control.style.display = aktif ? '' : 'none';
                        control.querySelectorAll('input').forEach(function (input) {
                            input.disabled = !aktif;
                            input.checked = aktif && isBenar;
                        });
                    });
                });
            }

            function refreshJumlahControls(card) {
                const opsiRows = card.querySelectorAll('[data-opsi-row]');
                const bolehHapusOpsi = opsiRows.length > MIN_OPSI;
                opsiRows.forEach(function (row) {
                    const tombol = row.querySelector('.remove-opsi-row');
                    if (tombol) tombol.classList.toggle('hidden', !bolehHapusOpsi);
                });
                const tombolTambahOpsi = card.querySelector('.add-opsi-row');
                if (tombolTambahOpsi) tombolTambahOpsi.classList.toggle('hidden', opsiRows.length >= MAX_OPSI);

                const pernyataanRows = card.querySelectorAll('[data-pernyataan-row]');
                const bolehHapusPernyataan = pernyataanRows.length > BATAS_HAPUS_PERNYATAAN;
                pernyataanRows.forEach(function (row) {
                    const tombol = row.querySelector('.remove-pernyataan-row');
                    if (tombol) tombol.classList.toggle('hidden', !bolehHapusPernyataan);
                });
                const tombolTambahPernyataan = card.querySelector('.add-pernyataan-row');
                if (tombolTambahPernyataan) tombolTambahPernyataan.classList.toggle('hidden', pernyataanRows.length >= MAX_PERNYATAAN);
            }

            function refreshCard(card) {
                toggleSections(card);
                refreshBenarControls(card);
                refreshJumlahControls(card);
            }

            // Kunci indeks selalu dirapikan agar nama field berurutan, `urutan`
            // tidak pernah lompat, dan penambahan baris berikutnya tidak bertabrakan.
            function reindexRows(card) {
                const cardIndex = card.dataset.index;
                [
                    ['[data-opsi-row]', 'opsi_jawaban'],
                    ['[data-pernyataan-row]', 'pernyataan_kategori'],
                ].forEach(function (konfig) {
                    const selector = konfig[0];
                    const field = konfig[1];
                    const pola = new RegExp('^daftar_soal\\[\\d+\\]\\[' + field + '\\]\\[\\d+\\]');
                    card.querySelectorAll(selector).forEach(function (row, pos) {
                        const prefix = 'daftar_soal[' + cardIndex + '][' + field + ']';
                        row.querySelectorAll('[name]').forEach(function (el) {
                            el.name = el.name.replace(pola, prefix + '[' + pos + ']');
                        });
                        const urutan = row.querySelector('input[name$="[urutan]"]');
                        if (urutan) urutan.value = String(pos + 1);
                        const radio = row.querySelector('input[type="radio"]');
                        if (radio) radio.value = String(pos);
                    });
                });
            }

            function htmlOpsiRow(cardIndex, oIdx) {
                const name = 'daftar_soal[' + cardIndex + '][opsi_jawaban][' + oIdx + ']';
                return [
                    '<input type="hidden" name="' + name + '[urutan]" value="' + (oIdx + 1) + '">',
                    '<div class="grid grid-cols-1 items-start gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">',
                        '<textarea name="' + name + '[teks_opsi]" rows="2" class="textarea" required></textarea>',
                        '<div class="flex flex-wrap items-center gap-3">',
                            '<input type="hidden" class="is-benar-hidden" name="' + name + '[is_benar]" value="0">',
                            '<label class="benar-control flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-700" data-opsi-for="pg">',
                                '<input type="radio" class="benar-radio accent-ink" name="benar_pilih_' + cardIndex + '" value="' + oIdx + '"> Benar',
                            '</label>',
                            '<label class="benar-control flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-700" data-opsi-for="pg_kompleks">',
                                '<input type="checkbox" class="benar-check h-4 w-4 rounded border-slate-300 accent-ink"> Benar',
                            '</label>',
                            '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-opsi-row">Hapus</button>',
                        '</div>',
                    '</div>',
                    '<div class="mt-3 grid grid-cols-3 gap-2 sm:max-w-64">',
                        irtHtml(name),
                    '</div>',
                ].join('');
            }

            function irtHtml(name) {
                const irt = [['a', '0.5–2.5', 'a_diskriminasi'], ['b', '−3–3', 'b_kesulitan'], ['c', '0–0.35', 'c_tebakan']];
                return irt.map(function (item) {
                    return '<label class="block">' +
                        '<span class="mb-1 block text-[11px] font-medium text-slate-500">' + item[0] +
                        ' <span class="text-slate-400">' + item[1] + '</span></span>' +
                        '<input type="number" step="0.01" name="' + name + '[' + item[2] + ']" ' +
                        'class="input w-full px-2 py-1.5 text-sm">' +
                        '</label>';
                }).join('');
            }

            function htmlPernyataanRow(cardIndex, pIdx, daftarKategori, kategoriTerpilih) {
                const name = 'daftar_soal[' + cardIndex + '][pernyataan_kategori][' + pIdx + ']';
                let optionsHtml = '<option value="">— pilih —</option>';
                daftarKategori.forEach(function (k) {
                    optionsHtml += '<option value="' + k + '"' + (k === kategoriTerpilih ? ' selected' : '') + '>' + k + '</option>';
                });

                return [
                    '<input type="hidden" name="' + name + '[urutan]" value="' + (pIdx + 1) + '">',
                    '<div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_auto]">',
                        '<input type="text" name="' + name + '[teks_pernyataan]" class="input" required placeholder="Teks pernyataan">',
                        '<select name="' + name + '[kategori_benar]" class="select kurasi-kategori-select" required>' + optionsHtml + '</select>',
                        '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-pernyataan-row">Hapus</button>',
                    '</div>',
                    '<div class="mt-3 grid grid-cols-3 gap-2 sm:max-w-64">' + irtHtml(name) + '</div>',
                ].join('');
            }

            function daftarKategori(card) {
                return Array.from(card.querySelectorAll('.kurasi-kategori-input'))
                    .map(function (input) { return input.value; })
                    .filter(function (v) { return v.trim(); });
            }

            form.querySelectorAll('.soal-card').forEach(function (card) {
                card.querySelector('.kurasi-tipe').addEventListener('change', function () { refreshCard(card); });

                const hapusCheck = card.querySelector('.dihapus-check');
                hapusCheck.addEventListener('change', function () {
                    card.classList.toggle('opacity-50', hapusCheck.checked);
                });

                refreshCard(card);
            });

            form.addEventListener('change', function (event) {
                const target = event.target;
                const card = target.closest('.soal-card');
                if (!card) return;

                if (target.matches('.benar-radio')) {
                    const baris = target.closest('[data-opsi-row]');
                    card.querySelectorAll('[data-opsi-row] .is-benar-hidden').forEach(function (hidden) {
                        hidden.value = '0';
                    });
                    baris.querySelector('.is-benar-hidden').value = '1';
                } else if (target.matches('.benar-check')) {
                    const baris = target.closest('[data-opsi-row]');
                    baris.querySelector('.is-benar-hidden').value = target.checked ? '1' : '0';
                } else if (target.matches('.kurasi-kategori-input')) {
                    const select = target.closest('.soal-card').querySelector('.kurasi-kategori-select');
                    if (select) refreshKategoriSelect(target.closest('.soal-card'), select);
                }
            });

            function refreshKategoriSelect(card, select) {
                const terpilih = select.value;
                const daftar = daftarKategori(card);
                select.innerHTML = '<option value="">— pilih —</option>' + daftar.map(function (k) {
                    return '<option value="' + k + '"' + (k === terpilih ? ' selected' : '') + '>' + k + '</option>';
                }).join('');
            }

            form.addEventListener('click', function (event) {
                const tombol = event.target.closest('button');
                if (!tombol) return;

                const card = tombol.closest('.soal-card');
                if (!card) return;

                if (tombol.classList.contains('add-kategori-row')) {
                    const container = card.querySelector('.kategori-list');
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'daftar_soal[' + card.dataset.index + '][daftar_kategori][]';
                    input.className = 'input kurasi-kategori-input w-40';
                    input.required = true;
                    container.insertBefore(input, tombol);
                    return;
                }

                if (tombol.classList.contains('add-opsi-row')) {
                    const container = card.querySelector('.opsi-list');
                    const jumlah = container.querySelectorAll('[data-opsi-row]').length;
                    const row = document.createElement('div');
                    row.className = 'mb-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3';
                    row.setAttribute('data-opsi-row', '');
                    row.innerHTML = htmlOpsiRow(card.dataset.index, jumlah);
                    container.appendChild(row);
                    reindexRows(card);
                    refreshCard(card);
                    return;
                }

                if (tombol.classList.contains('remove-opsi-row')) {
                    tombol.closest('[data-opsi-row]').remove();
                    reindexRows(card);
                    refreshCard(card);
                    return;
                }

                if (tombol.classList.contains('add-pernyataan-row')) {
                    const container = card.querySelector('.pernyataan-list');
                    const jumlah = container.querySelectorAll('[data-pernyataan-row]').length;
                    const row = document.createElement('div');
                    row.className = 'mb-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3';
                    row.setAttribute('data-pernyataan-row', '');
                    row.innerHTML = htmlPernyataanRow(card.dataset.index, jumlah, daftarKategori(card), '');
                    container.appendChild(row);
                    reindexRows(card);
                    refreshCard(card);
                    return;
                }

                if (tombol.classList.contains('remove-pernyataan-row')) {
                    tombol.closest('[data-pernyataan-row]').remove();
                    reindexRows(card);
                    refreshCard(card);
                }
            });

            function jalurNama(nama) {
                const hasil = [];
                const pola = /([^\[\]]+)|\[([^\]]*)\]/g;
                let cocok;
                while ((cocok = pola.exec(nama)) !== null) {
                    hasil.push(cocok[1] !== undefined ? cocok[1] : cocok[2]);
                }
                return hasil;
            }

            function setNilai(root, jalur, nilai) {
                let node = root;
                for (let k = 0; k < jalur.length - 1; k++) {
                    const key = jalur[k];
                    if (typeof node[key] !== 'object' || node[key] === null) {
                        node[key] = jalur[k + 1] === '' ? [] : {};
                    }
                    node = node[key];
                }
                const akhir = jalur[jalur.length - 1];
                if (akhir === '') {
                    if (!Array.isArray(node)) return;
                    node.push(nilai);
                } else {
                    node[akhir] = nilai;
                }
            }

            // Semua daftar soal dikirim dalam satu field JSON. PHP hanya mem-parse
            // application/x-www-form-urlencoded dengan batas max_input_vars (1000);
            // body JSON dibaca langsung sehingga tidak terkena batas itu.
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                const payload = {};
                Array.from(form.elements).forEach(function (el) {
                    if (el.disabled || !el.name) return;
                    if (el.name.indexOf('daftar_soal[') !== 0) return;
                    if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
                    setNilai(payload, jalurNama(el.name), el.value);
                });

                const daftar = payload.daftar_soal;
                if (!daftar || Object.keys(daftar).length === 0) {
                    tampilkanKesalahan('Tidak ada soal yang terbaca. Muat ulang halaman, lalu coba lagi.');
                    return;
                }

                jsonInput.value = JSON.stringify(daftar);

                // Kirim ulang lewat form baru berisi 4 field saja, sehingga
                // jumlah variabel POST jauh di bawah batas max_input_vars.
                const pengganti = document.createElement('form');
                pengganti.method = 'POST';
                pengganti.action = form.action;
                pengganti.hidden = true;

                ['_token', 'nama_paket', 'deskripsi', 'daftar_soal_json'].forEach(function (nama) {
                    const sumber = form.elements.namedItem(nama);
                    if (!sumber || sumber.disabled) return;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = nama;
                    input.value = sumber.value;
                    pengganti.appendChild(input);
                });

                document.body.appendChild(pengganti);
                pengganti.submit();
            });
        });
    </script>
</x-layouts.app>
