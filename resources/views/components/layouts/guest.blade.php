@props(['title' => 'Anfalm'])

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
    <header class="border-b border-slate-200 bg-white/80 backdrop-blur">
        <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4">
            <a href="{{ url('/') }}" class="text-xl font-extrabold tracking-tight text-ink">
                Anfa<span class="text-gold">lm</span>
            </a>

            <nav class="flex items-center gap-2 text-sm font-medium">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost">Masuk</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary">Daftar Gratis</a>
                    @endif
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-400">
        Anfalm — Tryout TKA untuk SD, SMP, SMA, dan SMK
    </footer>
</body>
</html>