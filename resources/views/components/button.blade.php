@props([
    'type' => 'submit',
    'variant' => 'primary',
])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }}
>
    {{ $slot }}
</button>