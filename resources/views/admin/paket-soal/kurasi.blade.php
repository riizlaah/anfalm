<x-layouts.app title="Kurasi Paket Soal dari AI">
    <h1 class="page-title">Kurasi Paket Soal dari AI</h1>

    <x-alert />
    <x-errors />

    @php
        $kurasiSoals = old('daftar_soal', $draft['daftar_soal'] ?? []);
        $namaPaket = old('nama_paket', $draft['nama_paket'] ?? '');
        $deskripsi = old('deskripsi', $draft['deskripsi'] ?? '');

        $soalKdMap = $kompetensiDasars->keyBy('kode_kompetensi');

        $soalViews = [];
        foreach ($kurasiSoals as $index => $s) {
            $kdKode = $s['kompetensi_dasar_kode'] ?? null;
            $kdId = (int) ($s['kompetensi_dasar_id'] ?? ($kdKode !== null ? ($soalKdMap[$kdKode]->id ?? 0) : 0));
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
                'opsi_rows' => $s['opsi_jawaban'] ?? [],
                'pernyataan_rows' => $s['pernyataan_kategori'] ?? [],
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

            <div class="card soal-card mt-5 p-6 @if ($sv['hapus']) opacity-50 @endif">
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

                    <label class="block">
                        <span class="label">a (diskriminasi)</span>
                        <input type="number" step="0.01" min="0.5" max="2.5" name="daftar_soal[{{ $i }}][a_diskriminasi]" class="input" value="{{ $sv['a'] }}">
                    </label>
                    <label class="block">
                        <span class="label">b (kesulitan)</span>
                        <input type="number" step="0.01" min="-3" max="3" name="daftar_soal[{{ $i }}][b_kesulitan]" class="input" value="{{ $sv['b'] }}">
                    </label>
                    <label class="block">
                        <span class="label">c (tebakan)</span>
                        <input type="number" step="0.01" min="0" max="0.35" name="daftar_soal[{{ $i }}][c_tebakan]" class="input" value="{{ $sv['c'] }}">
                    </label>
                </div>

                <section class="kurasi-opsi-section mt-5 border-t border-slate-200 pt-4">
                    <h3 class="mb-2 text-sm font-semibold text-ink">Opsi Jawaban (tepat 5)</h3>
                    @foreach ($opsiRows as $oIdx => $opsi)
                        <div class="mb-2 grid grid-cols-1 items-start gap-2 sm:grid-cols-[minmax(0,1fr)_4.5rem_1.5rem_auto]">
                            <input type="hidden" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][urutan]" value="{{ $opsi['urutan'] ?? $oIdx + 1 }}">
                            <textarea name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][teks_opsi]" rows="2" class="textarea" required>{{ $opsi['teks_opsi'] ?? '' }}</textarea>
                            <div class="flex items-center gap-1">
                                <input type="number" step="0.01" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][a_diskriminasi]" class="input px-2 py-1 text-xs" placeholder="a" value="{{ $opsi['a_diskriminasi'] ?? '' }}">
                                <input type="number" step="0.01" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][b_kesulitan]" class="input px-2 py-1 text-xs" placeholder="b" value="{{ $opsi['b_kesulitan'] ?? '' }}">
                                <input type="number" step="0.01" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][c_tebakan]" class="input px-2 py-1 text-xs" placeholder="c" value="{{ $opsi['c_tebakan'] ?? '' }}">
                            </div>
                            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <input type="hidden" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][is_benar]" value="0">
                                <input type="checkbox" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][is_benar]" value="1"
                                    class="h-4 w-4 rounded border-slate-300 accent-ink" @checked(! empty($opsi['is_benar']))>
                                Benar
                            </label>
                        </div>
                    @endforeach
                </section>

                <section class="kurasi-kategori-section mt-5 border-t border-slate-200 pt-4">
                    <h3 class="mb-2 text-sm font-semibold text-ink">Daftar Kategori</h3>
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($kategoriList as $kategori)
                            <input type="text" name="daftar_soal[{{ $i }}][daftar_kategori][]" value="{{ $kategori }}"
                                class="input kurasi-kategori-input w-40" required>
                        @endforeach
                        <button type="button" class="btn btn-ghost px-2.5 py-1 text-xs add-kategori-row">+ Kategori</button>
                    </div>

                    <h3 class="mb-2 text-sm font-semibold text-ink">Pernyataan (minimal 2)</h3>
                    @foreach ($pernyataanRows as $pIdx => $pernyataan)
                        <div class="mb-2 grid grid-cols-1 items-start gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_4.5rem]">
                            <input type="hidden" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][urutan]" value="{{ $pernyataan['urutan'] ?? $pIdx + 1 }}">
                            <input type="text" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][teks_pernyataan]" class="input" required
                                value="{{ $pernyataan['teks_pernyataan'] ?? '' }}" placeholder="Teks pernyataan">
                            <select name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][kategori_benar]" class="select kurasi-kategori-select" required>
                                <option value="">— pilih —</option>
                                @foreach ($kategoriList as $kategori)
                                    <option value="{{ $kategori }}" @selected(($pernyataan['kategori_benar'] ?? '') === $kategori)>{{ $kategori }}</option>
                                @endforeach
                            </select>
                            <div class="flex items-center gap-1">
                                <input type="number" step="0.01" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][a_diskriminasi]" class="input px-2 py-1 text-xs" placeholder="a" value="{{ $pernyataan['a_diskriminasi'] ?? '' }}">
                                <input type="number" step="0.01" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][b_kesulitan]" class="input px-2 py-1 text-xs" placeholder="b" value="{{ $pernyataan['b_kesulitan'] ?? '' }}">
                                <input type="number" step="0.01" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][c_tebakan]" class="input px-2 py-1 text-xs" placeholder="c" value="{{ $pernyataan['c_tebakan'] ?? '' }}">
                            </div>
                        </div>
                        <button type="button" class="mb-2 btn btn-ghost px-2.5 py-1 text-xs add-pernyataan-row" data-card-index="{{ $i }}">+ Tambah Pernyataan</button>
                    @endforeach
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

            function toggleSections(card) {
                const tipe = card.querySelector('.kurasi-tipe').value;
                card.querySelectorAll('.kurasi-opsi-section, .kurasi-kategori-section').forEach(function (section) {
                    const show = section.classList.contains('kurasi-opsi-section')
                        ? (tipe === 'pg' || tipe === 'pg_kompleks')
                        : (tipe === 'pg_kategori');
                    section.style.display = show ? '' : 'none';
                });
            }

            form.querySelectorAll('.soal-card').forEach(function (card) {
                const tipeSelect = card.querySelector('.kurasi-tipe');
                tipeSelect.addEventListener('change', function () { toggleSections(card); });
                toggleSections(card);

                const hapusCheck = card.querySelector('.dihapus-check');
                hapusCheck.addEventListener('change', function () {
                    card.classList.toggle('opacity-50', hapusCheck.checked);
                });
            });

            form.addEventListener('click', function (event) {
                if (event.target.classList.contains('add-kategori-row')) {
                    const card = event.target.closest('.soal-card');
                    const container = card.querySelector('.kurasi-kategori-section .flex');
                    const existing = container.querySelector('.kurasi-kategori-input');
                    const name = existing ? existing.name : '';
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = name;
                    input.className = 'input kurasi-kategori-input w-40';
                    input.required = true;
                    container.insertBefore(input, event.target);
                }

                if (event.target.classList.contains('add-pernyataan-row')) {
                    const card = event.target.closest('.soal-card');
                    const index = event.target.dataset.cardIndex;
                    const container = event.target.closest('.kurasi-kategori-section');
                    const select = container.querySelector('.kurasi-kategori-select');
                    const kategori = select ? select.value : '';
                    const daftar = Array.from(container.querySelectorAll('.kurasi-kategori-input')).map(function (input) { return input.value; }).filter(function (v) { return v.trim(); });

                    const pIdx = container.querySelectorAll('select.kurasi-kategori-select').length;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'mb-2 grid grid-cols-1 items-start gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_4.5rem]';

                    let optionsHtml = '<option value="">— pilih —</option>';
                    daftar.forEach(function (k) {
                        optionsHtml += '<option value="' + k + '"' + (k === kategori ? ' selected' : '') + '>' + k + '</option>';
                    });

                    wrapper.innerHTML = [
                        '<input type="hidden" name="daftar_soal[' + index + '][pernyataan_kategori][' + pIdx + '][urutan]" value="' + (pIdx + 1) + '">',
                        '<input type="text" name="daftar_soal[' + index + '][pernyataan_kategori][' + pIdx + '][teks_pernyataan]" class="input" required placeholder="Teks pernyataan">',
                        '<select name="daftar_soal[' + index + '][pernyataan_kategori][' + pIdx + '][kategori_benar]" class="select kurasi-kategori-select" required>' + optionsHtml + '</select>',
                        '<div class="flex items-center gap-1">' +
                            '<input type="number" step="0.01" name="daftar_soal[' + index + '][pernyataan_kategori][' + pIdx + '][a_diskriminasi]" class="input px-2 py-1 text-xs" placeholder="a">' +
                            '<input type="number" step="0.01" name="daftar_soal[' + index + '][pernyataan_kategori][' + pIdx + '][b_kesulitan]" class="input px-2 py-1 text-xs" placeholder="b">' +
                            '<input type="number" step="0.01" name="daftar_soal[' + index + '][pernyataan_kategori][' + pIdx + '][c_tebakan]" class="input px-2 py-1 text-xs" placeholder="c">' +
                        '</div>'
                    ].join('');

                    container.insertBefore(wrapper, event.target);
                    toggleSections(card);
                }
            });
        });
    </script>
</x-layouts.app>