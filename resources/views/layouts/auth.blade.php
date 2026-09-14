<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Anfalm</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #eef2f7 0%, #dfe7f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .brand { font-weight: 800; font-size: 1.75rem; color: #1e3a6f; letter-spacing: -0.02em; margin-bottom: 1.25rem; }
        .brand span { color: #d97706; }
        .card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 18px 40px -18px rgba(15, 33, 66, 0.35);
            padding: 2rem;
            width: 100%;
            max-width: 24rem;
        }
        h1 { font-size: 1.25rem; font-weight: 700; color: #0f2142; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin: 1rem 0 0.35rem; }
        input[type="email"], input[type="password"], input[type="text"], select {
            width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 0.95rem;
        }
        input:focus, select:focus { outline: 2px solid #2490ef55; border-color: #2490ef; }
        .btn {
            display: inline-block; margin-top: 1.5rem; width: 100%; text-align: center;
            padding: 0.7rem 1rem; border: 0; border-radius: 0.5rem; background: #1e3a6f; color: #fff;
            font-size: 0.95rem; font-weight: 600; cursor: pointer; text-decoration: none;
        }
        .btn:hover { background: #16305c; }
        .alt { margin-top: 1.25rem; font-size: 0.9rem; color: #4b5563; text-align: center; }
        .alt a { color: #2490ef; font-weight: 600; text-decoration: none; }
        .err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 0.6rem 0.85rem; font-size: 0.85rem; margin-top: 1rem; }
        .err p { margin: 0.15rem 0; }
    </style>
</head>
<body>
    <div class="brand">Anfa<span>lm</span></div>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>