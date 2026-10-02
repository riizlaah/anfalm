@props([
    'title' => 'Dashboard',
    /**
     * Proteksi konten 7.10; default aktif untuk peserta dan tidak pernah untuk
     * admin supaya WYSIWYG-nya tetap bisa menyalin dan menyeleksi teks.
     */
    'proteksi' => null,
    /**
     * Tab bar bawah untuk viewport kecil (Fase 10). Dimatikan di halaman
     * pengerjaan soal supaya tidak menutupi timer dan tidak memancing ketukan
     * tak sengaja di tengah ujian.
     */
    'tabbar' => true,
])

@php
    $lindungiKonten = $proteksi ?? (auth()->user()?->isPeserta() ?? false);

    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Tryout', 'route' => 'tryout.index'],
        ['label' => 'Latihan', 'route' => 'latihan.index'],
        ['label' => 'Analisis', 'route' => 'analisis.index'],
    ];

    if (auth()->check() && auth()->user()->isAdmin()) {
        $nav = array_merge($nav, [
            ['label' => 'Mapel', 'route' => 'admin.mapel.index'],
            ['label' => 'Paket Soal', 'route' => 'admin.paket-soal.index'],
            ['label' => 'Paket Tryout', 'route' => 'admin.paket-tryout.index'],
        ]);
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Anfalm</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['flex min-h-full flex-col', 'terlindungi' => $lindungiKonten])>
    <header class="bg-ink text-white">
        <div class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4">
            <a href="{{ route('dashboard') }}" class="shrink-0 text-lg font-extrabold tracking-tight">
                Anfa<span class="text-gold">lm</span>
            </a>

            <nav aria-label="Navigasi utama" class="hidden items-center gap-1 text-sm font-medium md:flex">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                        @class([
                            'rounded-md px-3 py-1.5 transition',
                            request()->routeIs($item['route'].'*') ? 'bg-white/10 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white',
                        ])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-slate-300 sm:block">{{ auth()->user()->nama_lengkap }}</span>
                <span
                    class="rounded-full border border-white/15 px-2.5 py-0.5 text-xs font-semibold text-slate-200">{{ auth()->user()->isAdmin() ? 'Admin' : 'Peserta' }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-md border border-white/20 px-3 py-1.5 text-sm font-medium text-slate-200 transition hover:bg-white/10 max-md:min-h-11">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main @class([
        'mx-auto w-full max-w-6xl flex-1 px-4 pt-8 sm:pt-10',
        'pb-[calc(4.5rem_+_env(safe-area-inset-bottom))] md:pb-10' => $tabbar,
        'pb-8 sm:pb-10' => ! $tabbar,
    ])>
        {{ $slot }}
    </main>

    @if ($tabbar)
        {{-- Nav khusus viewport kecil: menu desktop disembunyikan `md:flex`, jadi
             tanpa baris ini peserta di HP tidak punya jalan keluar sama sekali.
             Lebih dari empat menu (admin) digeser mendatar, bukan dipadatkan. --}}
        @php($rapat = count($nav) > 4)

        <nav id="tabbar-bawah" aria-label="Navigasi bawah"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-ink pb-[env(safe-area-inset-bottom)] md:hidden">
            <div @class(['flex', $rapat ? 'overflow-x-auto' : 'justify-between'])>
                @foreach ($nav as $item)
                    @php($aktif = request()->routeIs($item['route'].'*'))

                    <a href="{{ route($item['route']) }}"
                        @if ($aktif) aria-current="page" @endif
                        @class([
                            'py-3.5 text-center text-xs font-medium transition sm:text-sm',
                            'shrink-0 px-4' => $rapat,
                            'min-w-0 flex-1 px-1' => ! $rapat,
                            'bg-white/10 text-gold' => $aktif,
                            'text-slate-300 hover:bg-white/5 hover:text-white' => ! $aktif,
                        ])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif
</body>
</html>