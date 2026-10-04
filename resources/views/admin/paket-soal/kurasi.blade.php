<x-layouts.app title="Kurasi Paket Soal dari AI">
    <h1 class="page-title">Kurasi Paket Soal dari AI</h1>

    <x-alert />
    <x-errors />

    @if ($kdBelumCocok !== [])
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            {{ count($kdBelumCocok) }} soal belum punya KD yang cocok. Pilih Kompetensi Dasar secara manual
            pada soal yang ditandai di bawah.
        </div>
    @endif

    @php
        // Resolusi kode KD → id sudah dilakukan controller (termasuk saat halaman
        // dirender ulang dari old()), jadi view cukup membaca apa adanya.
        $kurasiSoals = $draft['daftar_soal'] ?? [];
        $namaPaket = old('nama_paket', $draft['nama_paket'] ?? '');
        $deskripsi = old('deskripsi', $draft['deskripsi'] ?? '');

        $soalKdMap = $kompetensiDasars->keyBy('kode_kompetensi');

        // Peta kode → data KD dipakai pemilih kode di seluruh kartu. Satu blok
        // JSON untuk halaman, bukan satu per kartu: daftarnya sama semua.
        $kdPeta = $kompetensiDasars
            ->filter(fn ($kd) => $kd->kode_kompetensi !== null)
            ->mapWithKeys(fn ($kd) => [
                $kd->kode_kompetensi => [
                    'id' => (int) $kd->id,
                    'deskripsi' => $kd->deskripsi,
                    'materi_pokok' => $kd->materi_pokok,
                ],
            ]);

        // Baris yang belum mencapai jumlah minimum dilengkapi agar kurasi
        // tetap bisa diisi tanpa harus menambah baris satu per satu.
        $soalViews = [];
        foreach ($kurasiSoals as $index => $s) {
            $kdKode = $s['kompetensi_dasar_kode'] ?? null;
            $kdId = (int) ($s['kompetensi_dasar_id'] ?? ($kdKode !== null ? ($soalKdMap[$kdKode]->id ?? 0) : 0));
            $kd = $kdId !== 0 ? $kompetensiDasars->firstWhere('id', $kdId) : null;
            $opsiRows = array_values($s['opsi_jawaban'] ?? []);
            $pernyataanRows = array_values($s['pernyataan_kategori'] ?? []);
            $kategoriRows = array_values(array_filter((array) ($s['daftar_kategori'] ?? ['Benar', 'Salah'])));

            $soalViews[] = [
                'index' => $index,
                'sementara' => $s['id_soal_sementara'] ?? '',
                'hapus' => ! empty($s['dihapus']),
                'terisi' => trim(strip_tags((string) ($s['pertanyaan'] ?? ''))) !== '',
                'tipe' => $s['tipe_soal'] ?? 'pg',
                'kd_id' => $kdId,
                'kd_kode' => $kd?->kode_kompetensi ?? (string) $kdKode,
                'kd_kode_terpilih' => $kd?->kode_kompetensi,
                'kd_deskripsi' => $kd?->deskripsi,
                'kd_materi' => $kd?->materi_pokok,
                'kode_kd' => $kdKode,
                'pertanyaan' => $s['pertanyaan'] ?? '',
                'pembahasan' => $s['pembahasan'] ?? '',
                'gambar_url' => $s['gambar_url'] ?? '',
                'a' => $s['a_diskriminasi'] ?? '',
                'b' => $s['b_kesulitan'] ?? '',
                'c' => $s['c_tebakan'] ?? '',
                'opsi_rows' => array_pad($opsiRows, max(count($opsiRows), 5), []),
                'pernyataan_rows' => array_pad($pernyataanRows, max(count($pernyataanRows), 3), []),
                'kategori_list' => array_pad($kategoriRows, max(count($kategoriRows), 2), ''),
            ];
        }

        // Tampilan satu-soal: yang tampil lebih dulu adalah soal pertama yang
        // ditolak validasi, supaya admin mendarat langsung pada masalahnya
        // alih-alih membuka soal pertama sementara galatnya ada di soal ke-7.
        $indeksBergalat = collect($errors->keys())
            ->map(fn (string $k) => preg_match('/^daftar_soal\.(\d+)(\.|$)/', $k, $m) === 1 ? (int) $m[1] : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $posisiAwal = in_array($indeksBergalat[0] ?? null, array_column($soalViews, 'index'), true)
            ? $indeksBergalat[0]
            : 0;
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

    {{-- novalidate: galat pada soal yang sedang tidak tampil harus membawa admin
         ke soal itu lebih dulu, dan hanya browser yang memblokir pengiriman
         sebelum script sempat berpindah — jadi pemeriksaan dilakukan sendiri. --}}
    <form method="POST" action="{{ route('admin.paket-soal.simpan') }}" id="kurasi-form" novalidate>
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

        {{--
            Navigasi berada di atas kartu soal, seperti pengerjaan tryout: satu
            soal tampil sekaligus, sisanya menunggu di balik rail nomor. Rail
            sengaja tidak dipindah ke bawah — di sini admin membuka halaman
            untuk menelusuri hasil generate, jadi peta keseluruhan lebih berguna
            ketimbang jempol yang harus menggali ke bawah kartu panjang.
        --}}
        <nav class="card mt-5 flex flex-wrap items-center justify-between gap-3 p-3" aria-label="Navigasi soal">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($soalViews as $sv)
                    <button type="button" class="rail-soal" data-indeks="{{ $sv['index'] }}"
                        data-terisi="{{ $sv['terisi'] ? '1' : '0' }}"
                        data-hapus="{{ $sv['hapus'] ? '1' : '0' }}"
                        data-aktif="{{ $sv['index'] === $posisiAwal ? '1' : '0' }}"
                        aria-label="Buka soal {{ $loop->iteration }}">
                        {{ $loop->iteration }}
                    </button>
                @endforeach
            </div>

            <p class="text-sm text-slate-500" id="kurasi-posisi">
                Soal {{ $posisiAwal + 1 }} dari {{ count($soalViews) }}
            </p>
        </nav>

        @foreach ($soalViews as $sv)
            @php
                $i = $sv['index'];
                $opsiRows = $sv['opsi_rows'];
                $pernyataanRows = $sv['pernyataan_rows'];
                $kategoriList = $sv['kategori_list'];
                $kategoriPilihan = array_values(array_filter($kategoriList));
                $irtMasihDefault = collect(['a' => 1.0, 'b' => 0.0, 'c' => 0.25])
                    ->every(fn (float $bawaan, string $kunci): bool => $sv[$kunci] === '' || $sv[$kunci] === null || (float) $sv[$kunci] === $bawaan);
            @endphp

            <section class="card soal-card mt-4 p-6 @if ($sv['hapus']) opacity-50 @endif"
                data-blok-soal="{{ $i }}" @if ($i !== $posisiAwal) hidden @endif data-index="{{ $i }}">

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

                {{-- Bentuk field di bawah ini mengikuti form soal manual
                     (components/admin/soal-form) apa adanya, termasuk urutan dan
                     kelasnya; yang menambah hanya data khusus kurasi: kode
                     mentah AI, penanda hapus, dan parameter IRT per baris. --}}
                <div class="mt-4 space-y-4">
                    <label class="block">
                        <span class="label">Tipe Soal</span>
                        <select name="daftar_soal[{{ $i }}][tipe_soal]" class="select kurasi-tipe" required>
                            @foreach (['pg' => 'Pilihan Ganda', 'pg_kompleks' => 'Pilihan Ganda Kompleks', 'pg_kategori' => 'Pilihan Ganda Kategori'] as $value => $label)
                                <option value="{{ $value }}" @selected($sv['tipe'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div>
                        <label class="block">
                            <span class="label">Kode Kompetensi Dasar</span>
                            <input type="text" class="input kurasi-kd-picker" list="kurasi-kd-list-{{ $i }}"
                                placeholder="Ketik kode KD…" autocomplete="off" value="{{ $sv['kd_kode'] }}">

                            <input type="hidden" name="daftar_soal[{{ $i }}][kompetensi_dasar_id]"
                                class="kurasi-kd-id" value="{{ $sv['kd_id'] !== 0 ? $sv['kd_id'] : '' }}">

                            {{-- Kode mentah dari AI ikut dikirim supaya saat submit gagal
                                 dan halaman dirender ulang, penandanya tetap bisa
                                 menyebut kode apa yang sebenarnya dikembalikan AI. --}}
                            <input type="hidden" name="daftar_soal[{{ $i }}][kompetensi_dasar_kode]" value="{{ $sv['kode_kd'] }}">

                            <datalist id="kurasi-kd-list-{{ $i }}">
                                @foreach ($kompetensiDasars as $kd)
                                    @continue($kd->kode_kompetensi === null)
                                    <option value="{{ $kd->kode_kompetensi }}"></option>
                                @endforeach
                            </datalist>
                        </label>

                        <p class="kurasi-kd-detail mt-2 text-xs leading-relaxed text-slate-600" @unless ($sv['kd_kode_terpilih']) hidden @endunless>
                            <span class="kurasi-kd-kode block font-medium text-ink">{{ $sv['kd_kode_terpilih'] }}</span>
                            <span class="kurasi-kd-deskripsi">{{ $sv['kd_deskripsi'] }}</span>
                            <span class="kurasi-kd-materi mt-0.5 block text-slate-500"
                                @unless ($sv['kd_materi']) hidden @endunless>Materi pokok: {{ $sv['kd_materi'] }}</span>
                        </p>

                        @if (in_array($i, $kdBelumCocok, true))
                            <p class="hint text-amber-700">
                                @if (trim((string) $sv['kode_kd']) === '')
                                    AI tidak menyebut kode KD — pilih secara manual.
                                @else
                                    Kode KD "{{ $sv['kode_kd'] }}" tidak ditemukan di {{ $mapel->nama }} — pilih secara manual.
                                @endif
                            </p>
                        @endif
                    </div>

                    <livewire:wysiwyg
                        nama="daftar_soal[{{ $i }}][pertanyaan]"
                        label="Pertanyaan"
                        :nilai="$sv['pertanyaan']" />

                    <livewire:wysiwyg
                        nama="daftar_soal[{{ $i }}][pembahasan]"
                        label="Pembahasan"
                        :nilai="$sv['pembahasan']" />

                    <label class="block">
                        <span class="label">URL Gambar <span class="font-normal text-slate-400">(opsional)</span></span>
                        <input type="url" name="daftar_soal[{{ $i }}][gambar_url]" class="input"
                            value="{{ $sv['gambar_url'] }}" placeholder="https://…">
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="label">a (diskriminasi)</span>
                            <input type="number" step="0.01" min="0.5" max="2.5"
                                name="daftar_soal[{{ $i }}][a_diskriminasi]" value="{{ $sv['a'] }}" class="input">
                        </label>
                        <label class="block">
                            <span class="label">b (kesulitan)</span>
                            <input type="number" step="0.01" min="-3" max="3"
                                name="daftar_soal[{{ $i }}][b_kesulitan]" value="{{ $sv['b'] }}" class="input">
                        </label>
                        <label class="block">
                            <span class="label">c (tebakan)</span>
                            <input type="number" step="0.01" min="0" max="0.35"
                                name="daftar_soal[{{ $i }}][c_tebakan]" value="{{ $sv['c'] }}" class="input">
                        </label>
                    </div>

                    @if ($irtMasihDefault)
                        <p class="hint text-amber-700">Parameter IRT masih default, disarankan untuk dikurasi.</p>
                    @endif

                    <section class="mt-6 border-t border-slate-200 pt-5" data-for-tipe="pg pg_kompleks">
                        <h2 class="mb-3 text-base font-semibold text-ink">Opsi Jawaban</h2>

                        <div class="opsi-list">
                            @foreach ($opsiRows as $oIdx => $opsi)
                                <div class="mb-4" data-opsi-row>
                                    <div class="opsi-row">
                                        <input type="hidden" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][urutan]" value="{{ $opsi['urutan'] ?? $oIdx + 1 }}">

                                        <input type="text" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][teks_opsi]"
                                            placeholder="Teks opsi" value="{{ $opsi['teks_opsi'] ?? '' }}" class="input" required>

                                        <input type="hidden" name="daftar_soal[{{ $i }}][opsi_jawaban][{{ $oIdx }}][is_benar]"
                                            value="{{ ! empty($opsi['is_benar']) ? '1' : '0' }}" class="is-benar-hidden">

                                        <label class="check benar-control" data-opsi-for="pg">
                                            <input type="radio" class="benar-radio" name="benar_pilih_{{ $i }}"
                                                value="{{ $oIdx }}" @checked(! empty($opsi['is_benar']))>
                                            Benar
                                        </label>
                                        <label class="check benar-control" data-opsi-for="pg_kompleks">
                                            <input type="checkbox" class="benar-check" @checked(! empty($opsi['is_benar']))>
                                            Benar
                                        </label>

                                        <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-opsi-row">Hapus</button>
                                    </div>

                                    {{-- Parameter IRT per opsi hanya ada di kurasi: AI
                                         sudah menaksirnya, dan form soal manual tidak
                                         menampilkannya karena opsinya memang diisi
                                         manual di sana. --}}
                                    <div class="mt-1 grid grid-cols-3 gap-2 sm:max-w-64">
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

                        <button type="button" class="btn btn-ghost add-opsi-row">+ Tambah Opsi</button>
                        <p class="hint">Minimal 5, maksimal 8 opsi (tombol Hapus muncul setelah jumlah melewati 5). PG: tepat 1 benar (pilih dengan radio). PG Kompleks: minimal 2 benar (centang dengan checkbox).</p>
                    </section>

                    <section class="mt-6 border-t border-slate-200 pt-5" data-for-tipe="pg_kategori">
                        <h2 class="mb-3 text-base font-semibold text-ink">Daftar Kategori</h2>

                        <div class="kategori-list">
                            @foreach ($kategoriList as $kategori)
                                <div class="kategori-row" data-kategori-row>
                                    <input type="text" name="daftar_soal[{{ $i }}][daftar_kategori][]"
                                        placeholder="Nama kategori (mis. Benar)" value="{{ $kategori }}"
                                        class="input kurasi-kategori-input" required>
                                    <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-kategori-row">Hapus</button>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn btn-ghost add-kategori-row">+ Tambah Kategori</button>

                        <h2 class="mt-5 mb-3 text-base font-semibold text-ink">Pernyataan</h2>

                        <div class="pernyataan-list">
                            @foreach ($pernyataanRows as $pIdx => $pernyataan)
                                <div class="mb-4" data-pernyataan-row>
                                    <div class="pernyataan-row">
                                        <input type="hidden" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][urutan]" value="{{ $pernyataan['urutan'] ?? $pIdx + 1 }}">

                                        <input type="text" name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][teks_pernyataan]"
                                            placeholder="Teks pernyataan" value="{{ $pernyataan['teks_pernyataan'] ?? '' }}" class="input" required>

                                        <select name="daftar_soal[{{ $i }}][pernyataan_kategori][{{ $pIdx }}][kategori_benar]" class="select kurasi-kategori-select" required>
                                            <option value="">— pilih —</option>
                                            @foreach ($kategoriPilihan as $kategori)
                                                <option value="{{ $kategori }}" @selected(($pernyataan['kategori_benar'] ?? '') === $kategori)>{{ $kategori }}</option>
                                            @endforeach
                                        </select>

                                        <button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-pernyataan-row">Hapus</button>
                                    </div>

                                    <div class="mt-1 grid grid-cols-3 gap-2 sm:max-w-64">
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

                        <button type="button" class="btn btn-ghost add-pernyataan-row">+ Tambah Pernyataan</button>
                        <p class="hint">Minimal 3 pernyataan (tombol Hapus muncul setelah jumlah melewati 3); kategori benar dipilih dari daftar kategori di atas.</p>
                    </section>
                </div>
            </section>
        @endforeach

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="btn btn-ghost" id="kurasi-sebelumnya" disabled>Sebelumnya</button>
            <button type="button" class="btn btn-ghost" id="kurasi-berikutnya">Berikutnya</button>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="hint">Pembahasan dapat berisi LaTeX \( \frac{1}{2} \). Parameter IRT (a, b, c) dapat disesuaikan di sini.</p>
            <div class="flex gap-2">
                <a href="{{ route('admin.paket-soal.generate') }}" class="btn btn-ghost">Generate Ulang</a>
                <button type="submit" class="btn btn-primary">Simpan Paket</button>
            </div>
        </div>
    </form>

    <script type="application/json" id="kurasi-kd-peta">@json($kdPeta)</script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('kurasi-form');
            const jsonInput = document.getElementById('daftar-soal-json');
            const kdPeta = JSON.parse(document.getElementById('kurasi-kd-peta').textContent || '{}');

            const MIN_OPSI = 5;
            const MAX_OPSI = 8;
            const BATAS_HAPUS_PERNYATAAN = 3;
            const MAX_PERNYATAAN = 5;

            const kartu = Array.from(form.querySelectorAll('[data-blok-soal]'));
            const rail = Array.from(form.querySelectorAll('[data-indeks]'));
            const tombolSebelum = document.getElementById('kurasi-sebelumnya');
            const tombolBerikut = document.getElementById('kurasi-berikutnya');
            const posisiSoal = document.getElementById('kurasi-posisi');
            let posisi = Math.max(0, kartu.findIndex(function (k) { return !k.hidden; }));

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

            // --- Tampilan satu-soal -------------------------------------------------

            function tampilkan(index, gulir) {
                if (kartu.length === 0) return;

                posisi = Math.max(0, Math.min(kartu.length - 1, index));
                kartu.forEach(function (k, n) { k.hidden = n !== posisi; });
                rail.forEach(function (t, n) { t.dataset.aktif = n === posisi ? '1' : '0'; });
                posisiSoal.textContent = 'Soal ' + (posisi + 1) + ' dari ' + kartu.length;
                tombolSebelum.disabled = posisi === 0;
                tombolBerikut.disabled = posisi === kartu.length - 1;
                refreshKartu(kartu[posisi]);
                perbaruiRail();

                if (gulir) kartu[posisi].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            rail.forEach(function (tombol) {
                tombol.addEventListener('click', function () { tampilkan(Number(tombol.dataset.indeks), true); });
            });
            tombolSebelum.addEventListener('click', function () { tampilkan(posisi - 1, true); });
            tombolBerikut.addEventListener('click', function () { tampilkan(posisi + 1, true); });

            // --- Pemilih kode Kompetensi Dasar (sama seperti form soal manual) ------

            function pasangKd(card) {
                const picker = card.querySelector('.kurasi-kd-picker');
                const hidden = card.querySelector('.kurasi-kd-id');
                const detail = card.querySelector('.kurasi-kd-detail');
                if (!picker || !hidden || !detail) return;

                // Nilai server dipertahankan selama admin belum mengetik apa pun,
                // supaya KD yang terpilih tapi tidak punya kode tidak kehilangan
                // id-nya begitu halaman dibuka.
                const idAwal = hidden.value;
                let disentuh = false;

                function sinkron() {
                    const kode = picker.value.trim();
                    const kd = kdPeta[kode];

                    if (kd) hidden.value = String(kd.id);
                    else if (disentuh) hidden.value = '';
                    else hidden.value = idAwal;

                    detail.hidden = !kd;
                    if (!kd) return;

                    detail.querySelector('.kurasi-kd-kode').textContent = kode;
                    detail.querySelector('.kurasi-kd-deskripsi').textContent = kd.deskripsi || '';
                    const materi = detail.querySelector('.kurasi-kd-materi');
                    materi.textContent = kd.materi_pokok ? 'Materi pokok: ' + kd.materi_pokok : '';
                    materi.hidden = !kd.materi_pokok;
                }

                function tandai() { disentuh = true; sinkron(); }

                picker.addEventListener('input', tandai);
                picker.addEventListener('change', tandai);
                sinkron();
            }

            // --- Isi kartu ----------------------------------------------------------

            function toggleSections(card) {
                const tipe = card.querySelector('.kurasi-tipe').value;
                card.querySelectorAll('section[data-for-tipe]').forEach(function (section) {
                    const show = (section.dataset.forTipe || '').split(' ').includes(tipe);
                    section.style.display = show ? 'block' : 'none';
                    // Nonaktifkan input section tersembunyi (bukan sekadar display:none)
                    // agar tidak ikut terkirim — sama seperti form soal manual.
                    section.querySelectorAll('input, select, textarea').forEach(function (el) {
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

                const kategoriRows = card.querySelectorAll('[data-kategori-row]');
                kategoriRows.forEach(function (row) {
                    const tombol = row.querySelector('.remove-kategori-row');
                    if (tombol) tombol.classList.toggle('hidden', kategoriRows.length <= 1);
                });
            }

            function refreshKartu(card) {
                if (!card) return;
                toggleSections(card);
                refreshBenarControls(card);
                refreshJumlahControls(card);
            }

            function pertanyaanTerisi(card) {
                const isi = (card.querySelector('[name$="[pertanyaan]"]') || {}).value || '';
                return isi.replace(/<[^>]*>/g, '').trim() !== '' || /<img\b/i.test(isi);
            }

            function perbaruiRail() {
                rail.forEach(function (tombol, n) {
                    const card = kartu[n];
                    if (!card) return;
                    tombol.dataset.terisi = pertanyaanTerisi(card) ? '1' : '0';
                    tombol.dataset.hapus = (card.querySelector('.dihapus-check') || {}).checked ? '1' : '0';
                });
            }

            // --- Baris opsi / pernyataan / kategori ---------------------------------

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

            function htmlOpsiRow(cardIndex, oIdx) {
                const name = 'daftar_soal[' + cardIndex + '][opsi_jawaban][' + oIdx + ']';
                return [
                    '<div class="opsi-row">',
                        '<input type="hidden" name="' + name + '[urutan]" value="' + (oIdx + 1) + '">',
                        '<input type="text" name="' + name + '[teks_opsi]" placeholder="Teks opsi" class="input" required>',
                        '<input type="hidden" class="is-benar-hidden" name="' + name + '[is_benar]" value="0">',
                        '<label class="check benar-control" data-opsi-for="pg">',
                            '<input type="radio" class="benar-radio" name="benar_pilih_' + cardIndex + '" value="' + oIdx + '"> Benar',
                        '</label>',
                        '<label class="check benar-control" data-opsi-for="pg_kompleks">',
                            '<input type="checkbox" class="benar-check"> Benar',
                        '</label>',
                        '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-opsi-row">Hapus</button>',
                    '</div>',
                    '<div class="mt-1 grid grid-cols-3 gap-2 sm:max-w-64">' + irtHtml(name) + '</div>',
                ].join('');
            }

            function htmlPernyataanRow(cardIndex, pIdx, daftarKategori, kategoriTerpilih) {
                const name = 'daftar_soal[' + cardIndex + '][pernyataan_kategori][' + pIdx + ']';
                let optionsHtml = '<option value="">— pilih —</option>';
                daftarKategori.forEach(function (k) {
                    optionsHtml += '<option value="' + k + '"' + (k === kategoriTerpilih ? ' selected' : '') + '>' + k + '</option>';
                });

                return [
                    '<div class="pernyataan-row">',
                        '<input type="hidden" name="' + name + '[urutan]" value="' + (pIdx + 1) + '">',
                        '<input type="text" name="' + name + '[teks_pernyataan]" placeholder="Teks pernyataan" class="input" required>',
                        '<select name="' + name + '[kategori_benar]" class="select kurasi-kategori-select" required>' + optionsHtml + '</select>',
                        '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-pernyataan-row">Hapus</button>',
                    '</div>',
                    '<div class="mt-1 grid grid-cols-3 gap-2 sm:max-w-64">' + irtHtml(name) + '</div>',
                ].join('');
            }

            function htmlKategoriRow(cardIndex) {
                return '<div class="kategori-row" data-kategori-row>' +
                    '<input type="text" name="daftar_soal[' + cardIndex + '][daftar_kategori][]" ' +
                    'placeholder="Nama kategori" class="input kurasi-kategori-input" required>' +
                    '<button type="button" class="btn btn-danger px-2.5 py-1 text-xs remove-kategori-row">Hapus</button>' +
                    '</div>';
            }

            function daftarKategori(card) {
                return Array.from(card.querySelectorAll('[data-kategori-row] input'))
                    .map(function (input) { return input.value; })
                    .filter(function (v) { return v.trim(); });
            }

            function refreshKategoriSelects(card) {
                const daftar = daftarKategori(card);
                card.querySelectorAll('.kurasi-kategori-select').forEach(function (select) {
                    const terpilih = select.value;
                    select.innerHTML = '<option value="">— pilih —</option>' + daftar.map(function (k) {
                        return '<option value="' + k + '"' + (k === terpilih ? ' selected' : '') + '>' + k + '</option>';
                    }).join('');
                });
            }

            form.querySelectorAll('.soal-card').forEach(function (card) {
                card.querySelector('.kurasi-tipe').addEventListener('change', function () { refreshKartu(card); });

                const hapusCheck = card.querySelector('.dihapus-check');
                hapusCheck.addEventListener('change', function () {
                    card.classList.toggle('opacity-50', hapusCheck.checked);
                    perbaruiRail();
                });

                pasangKd(card);
                refreshKartu(card);
            });

            form.addEventListener('input', perbaruiRail);
            // Editor kaya menyetel input tersembunyi lewat program, jadi tidak
            // pernah memancarkan event `input` — penanda rail diperbarui saat
            // admin menutup kolomnya.
            form.addEventListener('focusout', perbaruiRail);

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
                    refreshKategoriSelects(card);
                }

                perbaruiRail();
            });

            form.addEventListener('click', function (event) {
                const tombol = event.target.closest('button');
                if (!tombol || !form.contains(tombol)) return;

                const card = tombol.closest('.soal-card');
                if (!card) return;

                if (tombol.classList.contains('add-kategori-row')) {
                    const container = card.querySelector('.kategori-list');
                    const jumlah = container.querySelectorAll('[data-kategori-row]').length;
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = htmlKategoriRow(card.dataset.index);
                    const row = wrapper.firstElementChild;
                    row.querySelector('input').value = '';
                    container.appendChild(row);
                    refreshKategoriSelects(card);
                    refreshJumlahControls(card);
                    return;
                }

                if (tombol.classList.contains('remove-kategori-row')) {
                    tombol.closest('[data-kategori-row]').remove();
                    refreshKategoriSelects(card);
                    refreshJumlahControls(card);
                    return;
                }

                if (tombol.classList.contains('add-opsi-row')) {
                    const container = card.querySelector('.opsi-list');
                    const jumlah = container.querySelectorAll('[data-opsi-row]').length;
                    const row = document.createElement('div');
                    row.className = 'mb-4';
                    row.setAttribute('data-opsi-row', '');
                    row.innerHTML = htmlOpsiRow(card.dataset.index, jumlah);
                    container.appendChild(row);
                    reindexRows(card);
                    refreshKartu(card);
                    return;
                }

                if (tombol.classList.contains('remove-opsi-row')) {
                    tombol.closest('[data-opsi-row]').remove();
                    reindexRows(card);
                    refreshKartu(card);
                    return;
                }

                if (tombol.classList.contains('add-pernyataan-row')) {
                    const container = card.querySelector('.pernyataan-list');
                    const jumlah = container.querySelectorAll('[data-pernyataan-row]').length;
                    const row = document.createElement('div');
                    row.className = 'mb-4';
                    row.setAttribute('data-pernyataan-row', '');
                    row.innerHTML = htmlPernyataanRow(card.dataset.index, jumlah, daftarKategori(card), '');
                    container.appendChild(row);
                    reindexRows(card);
                    refreshKartu(card);
                    return;
                }

                if (tombol.classList.contains('remove-pernyataan-row')) {
                    tombol.closest('[data-pernyataan-row]').remove();
                    reindexRows(card);
                    refreshKartu(card);
                }
            });

            // --- Pemeriksaan isi sebelum kirim --------------------------------------

            function kartuGagal() {
                for (let n = 0; n < kartu.length; n++) {
                    const card = kartu[n];
                    if (card.querySelector('.dihapus-check')?.checked) continue;

                    // Editor kaya memakai input tersembunyi, dan `required` pada
                    // input tersembunyi diabaikan browser — jadi pertanyaan yang
                    // kosong, atau yang hanya berisi <p></p>, diperiksa di sini.
                    if (!pertanyaanTerisi(card)) {
                        return { indeks: n, pesan: 'Soal ' + (n + 1) + ' belum memiliki pertanyaan.' };
                    }

                    const elemen = Array.from(card.querySelectorAll('[required]'))
                        .find(function (el) { return !el.disabled && !el.checkValidity(); });
                    if (elemen) return { indeks: n, elemen: elemen };
                }

                return null;
            }

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

                const gagal = kartuGagal();
                if (gagal) {
                    // Berpindah dulu ke soalnya: galat yang diteriakkan pada kartu
                    // tersembunyi tidak terlihat siapa pun.
                    tampilkan(gagal.indeks, false);

                    if (gagal.elemen) gagal.elemen.reportValidity();
                    else tampilkanKesalahan(gagal.pesan);

                    return;
                }

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

            tampilkan(posisi, false);
        });
    </script>
</x-layouts.app>
