{{-- Markup editornya kini satu sumber di `components/editor.blade.php` (butir 165);
     komponen ini hanya memasok mode penuh plus baris unggah gambar miliknya. --}}
<x-editor :nama="$nama" :nilai="$nilai" :label="$label">
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
</x-editor>
