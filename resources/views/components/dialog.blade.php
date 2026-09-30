@props([
    'id',
    'title' => '',
])

<dialog id="{{ $id }}" {{ $attributes->merge(['class' => 'dialog']) }} aria-labelledby="{{ $id }}-title">
    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
        <h2 id="{{ $id }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
        <button type="button" class="rounded-md px-2 py-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
            data-dialog-close aria-label="Tutup">&times;</button>
    </div>

    <div class="max-h-[calc(100vh-10rem)] overflow-y-auto px-5 py-4">
        {{ $slot }}
    </div>
</dialog>
