@props([
    'label' => '',
    'name' => '',
    'type' => 'text',
    'value' => null,
])

<label class="block">
    @if ($label)
        <span class="label">{{ $label }}</span>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $type !== 'password' ? old($name, $value) : '' }}"
        {{ $attributes->merge(['class' => 'input']) }}
    >
</label>