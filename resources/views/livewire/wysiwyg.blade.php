<div data-wysiwyg data-wysiwyg-nama="{{ $nama }}">
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
            <button type="button" data-tugas="daftar-bulat" title="Daftar berbutir">•</button>
            <button type="button" data-tugas="daftar-bernomor" title="Daftar bernomor">1.</button>
            <button type="button" data-tugas="kutipan" title="Kutipan">❝</button>
            <button type="button" data-tugas="kode" title="Blok kode">&lt;/&gt;</button>
            <span class="wysiwyg-pemisah" aria-hidden="true"></span>
            <button type="button" data-tugas="rumus" title="Sisipkan ekspresi matematika KaTeX">ƒ(x)</button>
            <button type="button" data-tugas="gambar" title="Sisipkan gambar">🖼</button>
        </div>

        <div data-wysiwyg-editor class="wysiwyg-kolom-wadah"></div>

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

                <label class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" data-rumus-mode-blok class="size-4 rounded border-slate-300">
                    Tampilkan sebagai blok terpusat
                </label>

                <span class="label mt-3">Pratinjau</span>
                <div data-rumus-pratinjau class="wysiwyg-rumus-pratinjau"></div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-rumus-batal class="btn btn-ghost">Batal</button>
                    <button type="button" data-rumus-simpan class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </dialog>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-3">
        <label class="text-xs font-medium text-slate-600">
            Sisipkan gambar
            <input type="file" accept="image/webp,image/png,image/jpeg" wire:model="gambar"
                class="block mt-1 text-xs">
        </label>

        <button type="button" wire:click="unggahGambar" wire:loading.attr="disabled"
            wire:target="gambar,unggahGambar" class="btn btn-ghost text-xs">
            <span wire:loading.remove wire:target="gambar,unggahGambar">Unggah &amp; sisipkan</span>
            <span wire:loading wire:target="gambar,unggahGambar">Mengunggah…</span>
        </button>
    </div>

    @error('gambar')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
