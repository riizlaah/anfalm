@props([
    'label' => '',
    'name' => '',
    'value' => null,
    'rows' => 4,
])

<label class="block">
    @if ($label)
        <span class="label">{{ $label }}</span>
    @endif

    <textarea
        name="{{ $name }}"
        rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'textarea']) }}
    >{{ old($name, $value) }}</textarea>
</label>