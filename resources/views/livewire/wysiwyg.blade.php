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
            <button type="button" data-tugas="rumus" title="Sisipkan rumus KaTeX: \( ... \) atau \[ ... \]">ƒ(x)</button>
            <button type="button" data-tugas="gambar" title="Sisipkan gambar">🖼</button>
            <span class="wysiwyg-pemisah" aria-hidden="true"></span>
            <button type="button" data-tugas="preview" title="Pratinjau KaTeX" aria-pressed="false">Pratinjau</button>
        </div>

        <div data-wysiwyg-editor class="wysiwyg-kolom-wadah"></div>

        <div data-wysiwyg-preview class="wysiwyg-preview hidden"></div>
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
