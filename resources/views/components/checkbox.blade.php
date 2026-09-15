@props([
    'name' => '',
    'label' => '',
    'checked' => false,
])

<label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
    <input
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked($checked || old($name))
        {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-slate-300 accent-ink']) }}
    >
    <span>{{ $label }}</span>
</label>