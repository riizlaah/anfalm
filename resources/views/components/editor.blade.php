@props(['nama', 'nilai' => '', 'label' => '', 'mode' => 'penuh', 'placeholder' => ''])

{{--
    Satu markup editor WYSIWYG untuk dua pemakaian (butir 165):

    - `mode="penuh"` — kolom utama pertanyaan/pembahasan lewat `livewire:wysiwyg`:
      daftar, kutipan, blok kode, gambar, dan ekspresi mode blok ikut tersedia.
    - `mode="inline"` — baris opsi jawaban dan pernyataan PG Kategori. Keduanya
      dirender di dalam `<label>` pada halaman peserta, yang menurut spesifikasi
      tidak boleh memuat konten blok, jadi editornya hanya penanda di dalam baris
      (tebal, miring, garis bawah, dicoret, kode) plus ekspresi yang selalu
      menempel. Gambar dan unggahannya sengaja tidak ada.

    Slot dipakai `livewire/wysiwyg.blade.php` untuk baris unggah gambar yang
    hanya dimiliki mode penuh.
--}}
<div data-wysiwyg data-wysiwyg-mode="{{ $mode }}" data-wysiwyg-nama="{{ $nama }}">
    @if ($label !== '')
        <span class="label">{{ $label }}</span>
    @endif

    {{-- Area editor di-*ignore*: nilainya dikelola TipTap di browser dan tidak
         boleh disentuh morph Livewire, termasuk setelah unggahan gambar. --}}
    <div wire:ignore>
        <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}" data-wysiwyg-input>

        <div class="wysiwyg-toolbar" role="toolbar" aria-label="Pemformatan teks">
            <button type="button" data-tugas="bold" title="Tebal (Ctrl+B)"><strong>B</strong></button>
            <button type="button" data-tugas="italic" title="Miring (Ctrl+I)"><em>I</em></button>
            <button type="button" data-tugas="underline" title="Garis bawah (Ctrl+U)"><u>U</u></button>
            <button type="button" data-tugas="strike" title="Dicoret"><s>S</s></button>
            <span class="wysiwyg-pemisah" aria-hidden="true"></span>
            @if ($mode === 'inline')
                <button type="button" data-tugas="kode" title="Kode">&lt;/&gt;</button>
            @else
                <button type="button" data-tugas="daftar-bulat" title="Daftar berbutir">•</button>
                <button type="button" data-tugas="daftar-bernomor" title="Daftar bernomor">1.</button>
                <button type="button" data-tugas="kutipan" title="Kutipan">❝</button>
                <button type="button" data-tugas="kode" title="Blok kode">&lt;/&gt;</button>
            @endif
            <span class="wysiwyg-pemisah" aria-hidden="true"></span>
            <button type="button" data-tugas="rumus" title="Sisipkan ekspresi matematika KaTeX">ƒ(x)</button>
            @if ($mode !== 'inline')
                <button type="button" data-tugas="gambar" title="Sisipkan gambar">🖼</button>
            @endif
        </div>

        <div data-wysiwyg-editor class="wysiwyg-kolom-wadah"
            @if ($placeholder !== '') data-placeholder="{{ $placeholder }}" @endif></div>

        {{-- Tidak ada tombol pratinjau (butir 163): isi kolom sudah dirender KaTeX
             oleh node `ekspresi`, sehingga yang terlihat di editor itulah persis
             yang tersimpan. Keduanya dulu terpisah dan admin harus menekan
             "Pratinjau" untuk tahu hasilnya. --}}
        <dialog data-rumus-dialog class="dialog" aria-label="Ekspresi matematika">
            <div class="p-4">
                <p class="card-title">Ekspresi matematika</p>

                <label class="mt-3 block">
                    <span class="label">Kode KaTeX</span>
                    <input type="text" data-rumus-input class="input mt-1 font-mono"
                        placeholder="\frac{2}{3}" autocomplete="off" spellcheck="false">
                </label>

                @if ($mode !== 'inline')
                    {{-- Blok terpusat hanya masuk akal di konten blok; opsi dan
                         pernyataan selalu menempel di dalam baris teksnya. --}}
                    <label class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" data-rumus-mode-blok class="size-4 rounded border-slate-300">
                        Tampilkan sebagai blok terpusat
                    </label>
                @endif

                <span class="label mt-3">Pratinjau</span>
                <div data-rumus-pratinjau class="wysiwyg-rumus-pratinjau"></div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-rumus-batal class="btn btn-ghost">Batal</button>
                    <button type="button" data-rumus-simpan class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </dialog>
    </div>

    {{ $slot }}
</div>
