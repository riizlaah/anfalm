@props([
    'label' => '',
    'name' => '',
    'value' => null,
])

<label class="block">
    @if ($label)
        <span class="label">{{ $label }}</span>
    @endif

    <textarea
        name="{{ $name }}"
        rows="4"
        {{ $attributes->merge(['class' => 'textarea']) }}
    >{{ old($name, $value) }}</textarea>
</label>