<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Anfalm</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #f4f6fa; min-height: 100vh; display: flex; flex-direction: column;
        }
        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            background: #0f2142; color: #fff; padding: 0.9rem 1.5rem;
        }
        .topbar .brand { font-weight: 800; font-size: 1.15rem; }
        .topbar .brand span { color: #fbbf24; }
        .userbox { display: flex; align-items: center; gap: 0.9rem; font-size: 0.9rem; }
        .chip {
            background: #2563eb22; color: #93c5fd; border: 1px solid #2563eb55;
            padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700;
        }
        .logout {
            background: transparent; color: #fca5a5; border: 1px solid #7f1d1d55;
            padding: 0.35rem 0.8rem; border-radius: 0.5rem; cursor: pointer; font-size: 0.85rem;
        }
        .logout:hover { background: #7f1d1d33; }
        main { flex: 1; padding: 2rem 1.5rem; max-width: 72rem; width: 100%; margin: 0 auto; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #0f2142; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">Anfa<span>lm</span></div>
        <nav class="userbox">
            <span>{{ auth()->user()->nama_lengkap }}</span>
            <span class="chip">{{ auth()->user()->isAdmin() ? 'Admin' : 'Peserta' }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout" type="submit">Keluar</button>
            </form>
        </nav>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>