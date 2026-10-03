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
    $admin = auth()->check() && auth()->user()->isAdmin();

    // Latihan dan Analisis adalah alur peserta: muncul di menu admin hanya
    // membingungkan karena keduanya bukan pekerjaan admin.
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'ikon' => 'dashboard'],
        ['label' => 'Tryout', 'route' => 'tryout.index', 'ikon' => 'tryout'],
    ];

    if ($admin) {
        $nav[] = ['label' => 'Mapel', 'route' => 'admin.mapel.index', 'ikon' => 'mapel'];
        $nav[] = ['label' => 'Paket Soal', 'route' => 'admin.paket-soal.index', 'ikon' => 'paket-soal'];
        $nav[] = ['label' => 'Paket Tryout', 'route' => 'admin.paket-tryout.index', 'ikon' => 'paket-tryout'];
    } else {
        $nav[] = ['label' => 'Latihan', 'route' => 'latihan.index', 'ikon' => 'latihan'];
        $nav[] = ['label' => 'Analisis', 'route' => 'analisis.index', 'ikon' => 'analisis'];
    }

    // Profil adalah tempat mengganti pilihan mapel (butir 96), jadi ia harus
    // bisa dicapai dari mana pun, bukan hanya lewat tautan setelah pendaftaran.
    $nav[] = ['label' => 'Profil', 'route' => 'profil.show', 'ikon' => 'profil'];
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
    {{-- Chrome terang (butir "UI cukup gelap"): navy tidak lagi dipakai sebagai
         permukaan yang menutupi layar, hanya sebagai aksen — lencana merek dan
         pil menu aktif. Emas tetap di atas navy sehingga kontrasnya terjaga. --}}
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4">
            <a href="{{ route('dashboard') }}"
                class="shrink-0 rounded-lg bg-ink px-2.5 py-1 text-lg font-extrabold tracking-tight text-white">
                Anfa<span class="text-gold">lm</span>
            </a>

            <nav aria-label="Navigasi utama" class="hidden items-center gap-1 text-sm font-medium md:flex">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                        @class([
                            'rounded-lg px-3 py-1.5 transition',
                            request()->routeIs($item['route'].'*') ? 'bg-ink text-gold' : 'text-slate-600 hover:bg-slate-100 hover:text-ink',
                        ])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-slate-600 sm:block">{{ auth()->user()->nama_lengkap }}</span>
                <span
                    class="rounded-full border border-slate-300 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ auth()->user()->isAdmin() ? 'Admin' : 'Peserta' }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 max-md:min-h-11">
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
             Lebih dari empat menu digeser mendatar, bukan dipadatkan — dan
             `px-2` (bukan `px-4`) adalah harga yang dibayar supaya lima menu
             peserta masih muat utuh di 360px: dengan padding ganda, "Profil"
             terdorong 67px keluar layar dan tidak pernah terlihat. --}}
        @php($rapat = count($nav) > 4)

        <nav id="tabbar-bawah" aria-label="Navigasi bawah"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
            <div @class(['flex', 'tabbar-scroll overflow-x-auto' => $rapat, 'justify-between' => ! $rapat])>
                @foreach ($nav as $item)
                    @php($aktif = request()->routeIs($item['route'].'*'))

                    {{-- Ikon di atas label, bukan di sampingnya: empat tab selebar
                         90px tidak cukup untuk keduanya berjalan mendatar. Label
                         tetap ikut dirender sebagai nama aksesibel tautan. --}}
                    <a href="{{ route($item['route']) }}"
                        @if ($aktif) aria-current="page" @endif
                        @class([
                            'mx-1 flex flex-col items-center justify-center gap-1 whitespace-nowrap rounded-lg py-2.5 text-center text-xs font-medium transition sm:text-sm',
                            'shrink-0 px-2' => $rapat,
                            'min-w-0 flex-1 px-1' => ! $rapat,
                            'bg-ink text-gold' => $aktif,
                            'text-slate-500 hover:bg-slate-100 hover:text-ink' => ! $aktif,
                        ])>
                        <x-icon :name="$item['ikon']" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endif
</body>
</html>