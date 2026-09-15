@props(['title' => 'Dashboard'])

@php
    $nav = [['label' => 'Dashboard', 'route' => 'dashboard']];

    if (auth()->check() && auth()->user()->isAdmin()) {
        $nav = array_merge($nav, [
            ['label' => 'Mapel', 'route' => 'admin.mapel.index'],
            ['label' => 'Kompetensi Dasar', 'route' => 'admin.kompetensi-dasar.index'],
            ['label' => 'Soal', 'route' => 'admin.soal.index'],
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
<body class="flex min-h-full flex-col">
    <header class="bg-ink text-white">
        <div class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4">
            <a href="{{ route('dashboard') }}" class="shrink-0 text-lg font-extrabold tracking-tight">
                Anfa<span class="text-gold">lm</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium md:flex">
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
                        class="rounded-md border border-white/20 px-3 py-1.5 text-sm font-medium text-slate-200 transition hover:bg-white/10">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:py-10">
        {{ $slot }}
    </main>
</body>
</html>