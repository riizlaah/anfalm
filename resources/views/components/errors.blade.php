{{--
    `only` membatasi daftar pada kunci tertentu: satu halaman bisa memuat
    beberapa form terpisah, dan tanpa batas ini setiap galat muncul dua kali —
    sekali di bawah form yang salah dan sekali di bawah form yang benar.
    Pencocokannya per awalan, jadi `mapel_pilihan` ikut menangkap
    `mapel_pilihan.0` (galat per elemen larik).
--}}
@props(['only' => null])

@php
    $daftarError = $only === null
        ? $errors->all()
        : collect($errors->messages())
            ->filter(fn (array $pesan, string $kunci): bool => collect($only)->contains(
                fn (string $pembatas): bool => $kunci === $pembatas || str_starts_with($kunci, $pembatas.'.')
            ))
            ->flatten()
            ->all();
@endphp

@if (count($daftarError) > 0)
    <div {{ $attributes->merge(['class' => 'mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700']) }}>
        <p class="font-semibold">Periksa kembali isian berikut:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($daftarError as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
