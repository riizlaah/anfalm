@props([])

@php
    $kinds = [
        ['type' => 'success', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        ['type' => 'error', 'class' => 'border-red-200 bg-red-50 text-red-700'],
    ];
@endphp

@foreach ($kinds as $kind)
    @if (session($kind['type']))
        <div {{ $attributes->merge(['class' => 'mb-5 rounded-lg border p-3 text-sm '.$kind['class']]) }}>
            {{ session($kind['type']) }}
        </div>
    @endif
@endforeach