@props([
    'streak' => 0,
    'hariAktif' => 0,
    'minggu' => [],
])

{{--
    Kalender aktivitas gaya kontribusi: satu sel per hari, tujuh baris per
    minggu, delapan minggu terakhir. Dipakai di Dashboard peserta sebagai
    gambaran seberapa rajin belajar — angka yang bisa langsung dipahami tanpa
    penjelasan, bukan statistik psikometrik.

    Selnya murni hiasan data, bukan tombol: tidak ada tindakan yang bisa
    dilakukan atas satu hari tertentu, jadi seluruh isi grid disembunyikan dari
    pembaca layar dan digantikan ringkasan pada `aria-label` di wadahnya.
--}}

@php
    $tingkatKelas = [
        0 => 'bg-slate-100',
        1 => 'bg-emerald-200',
        2 => 'bg-emerald-400',
        3 => 'bg-emerald-600',
    ];

    $labelHari = ['Sen', '', 'Rab', '', 'Jum', '', 'Min'];
@endphp

<section {{ $attributes->merge(['class' => 'card p-6']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="card-title">
                Streak belajar
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                @if ($hariAktif === 0)
                    Belum ada aktivitas. Kerjakan satu latihan hari ini untuk memulai streak.
                @else
                    Satu kotak per hari, {{ count($minggu) }} minggu terakhir.
                @endif
            </p>
        </div>

        <div class="shrink-0 text-right">
            <p class="text-3xl leading-none font-bold text-ink" data-streak="{{ $streak }}">{{ $streak }}</p>
            <p class="mt-1 text-xs text-slate-500">hari beruntun</p>
        </div>
    </div>

    <div class="mt-4 flex gap-2"
         role="img"
         aria-label="Kalender aktivitas {{ count($minggu) }} minggu terakhir: {{ $hariAktif }} hari aktif, {{ $streak }} hari beruntun">
        <div class="flex flex-col gap-1" aria-hidden="true">
            @foreach ($labelHari as $label)
                <span class="flex h-3.5 w-5 items-center text-[10px] leading-none text-slate-400">{{ $label }}</span>
            @endforeach
        </div>

        <div class="flex gap-1" aria-hidden="true">
            @foreach ($minggu as $selMinggu)
                <div class="flex flex-col gap-1">
                    @foreach ($selMinggu as $sel)
                        <time datetime="{{ $sel['tanggal'] }}"
                              data-tanggal="{{ $sel['tanggal'] }}"
                              data-tingkat="{{ $sel['tingkat'] }}"
                              title="{{ $sel['tanggal'] }} · {{ $sel['jumlah'] }} percobaan"
                              @class(['h-3.5 w-3.5 rounded-[3px]', $tingkatKelas[$sel['tingkat']]])></time>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <span>{{ $hariAktif }} hari aktif</span>

        <span class="flex items-center gap-1">
            Sedikit

            @foreach (array_keys($tingkatKelas) as $tingkat)
                <span @class(['h-3 w-3 rounded-[3px]', $tingkatKelas[$tingkat]])></span>
            @endforeach

            Banyak
        </span>
    </div>
</section>
