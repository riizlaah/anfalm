@props([])

@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700']) }}>
        <p class="font-semibold">Periksa kembali isian berikut:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif