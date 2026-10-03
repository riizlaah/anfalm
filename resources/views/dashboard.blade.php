<x-layouts.app :title="'Dashboard'">
    {{--
        Sapaan berdiri di atas latar halaman, bukan di dalam kartu: seluruh
        halaman lain (Latihan, Analisis, Tryout, Dashboard Admin) memakai pola
        `page-title` + subteks, lalu kartu berikutnya. Kartu yang hanya berisi
        dua baris teks memakan satu kotak putih tanpa ada yang bisa ditindak-
        lanjuti di dalamnya.
    --}}
    <h1 class="page-title">Halo, {{ auth()->user()->nama_lengkap }}!</h1>

    @if (auth()->user()->isAdmin())
        <p class="mt-1.5 text-slate-600">
            Anda masuk sebagai <strong class="font-semibold text-ink">Admin</strong>.
        </p>

        {{--
            Cabang admin tidak punya kartu apa pun (aktivitas belajar hanya milik
            peserta), jadi tanpa ajakan ini halaman tinggal dua baris teks di
            tengah layar kosong. Tombolnya mengambil peran yang semula dipegang
            tautan di dalam kalimat.
        --}}
        <div class="mt-5">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Kelola konten di area admin</a>
        </div>
    @else
        <p class="mt-1.5 text-slate-600">
            Selamat datang di platform tryout TKA. Pengerjaan tryout akan tersedia di sini.
        </p>
    @endif

    @if ($aktivitas !== null)
        <x-streak-aktivitas
            class="mt-6 max-w-3xl"
            :streak="$aktivitas['streak']"
            :hari-aktif="$aktivitas['hari_aktif']"
            :minggu="$aktivitas['minggu']" />
    @endif
</x-layouts.app>
