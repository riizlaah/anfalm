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
<body class="relative flex min-h-full items-center justify-center overflow-hidden bg-ink px-4 py-10 text-white">
    <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-gold/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-sky-500/10 blur-3xl"></div>

    <main class="relative w-full max-w-sm">
        <div class="mb-7 text-center">
            <a href="{{ url('/') }}" class="text-3xl font-extrabold tracking-tight">
                Anfa<span class="text-gold">lm</span>
            </a>
            <p class="mt-1.5 text-sm text-slate-300">Tryout TKA dengan skoring berbasis IRT</p>
        </div>

        <div class="rounded-2xl bg-white p-6 text-slate-800 shadow-xl sm:p-7">
            {{ $slot }}
        </div>
    </main>
</body>
</html>