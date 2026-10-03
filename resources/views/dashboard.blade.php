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

    {{--
        Kartu tryout hanya sampai ke peserta (`$tryoutTerbaru` null untuk admin),
        dan isinya berubah menurut satu perbedaan: `hasil` ada atau tidak. Bila
        belum ada, kartu ini mengajak mengerjakan; begitu nilainya keluar, ayat
        dan tombolnya berganti menjadi hasil — bukan sekadar label yang ditukar,
        karena angka skornya sendiri ikut tampil. Keadaan "belum ada paket"
        disampaikan tanpa tombol: tidak ada yang bisa ditekan, dan mengarahkan
        ke halaman yang sendirinya hanya berisi "belum ada paket" hanya
        menambah satu klik pada pesan yang sama.
    --}}
    @if ($tryoutTerbaru !== null)
        @php($paketTryout = $tryoutTerbaru['paket'])
        @php($hasil = $tryoutTerbaru['hasil'])

        <section class="card mt-6 p-6 max-w-3xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <p class="label">Tryout terbaru</p>

                    @if ($paketTryout === null)
                        <p class="mt-1 text-sm text-slate-600">
                            Belum ada paket tryout yang tersedia. Kartu ini akan
                            muncul begitu admin menerbitkan paketnya.
                        </p>
                    @else
                        @php($namaMapel = collect([
                            $paketTryout->wajib1,
                            $paketTryout->wajib2,
                            $paketTryout->wajib3,
                            $paketTryout->pilihan1,
                            $paketTryout->pilihan2,
                        ])->filter()->pluck('nama')->implode(', '))

                        <h2 class="mt-1 text-lg font-semibold text-ink">{{ $paketTryout->nama_paket }}</h2>

                        @if ($paketTryout->deskripsi)
                            <p class="mt-1 text-sm text-slate-600">{{ $paketTryout->deskripsi }}</p>
                        @endif

                        <p class="mt-2 text-xs text-slate-500">
                            Tingkat {{ $paketTryout->tingkat }} ·
                            {{ $paketTryout->batas_waktu_menit }} menit ·
                            {{ $namaMapel }}
                        </p>

                        @if ($hasil === null)
                            <p class="mt-3 text-sm font-semibold text-ink">Belum kamu selesaikan</p>

                            <p class="mt-1 text-sm text-slate-600">
                                Skormu baru muncul setelah paket ini dikerjakan
                                sampai tuntas.
                            </p>
                        @else
                            <p class="label mt-3">Skor</p>

                            <p class="mt-1 text-3xl font-bold text-ink">{{ $hasil->skor_konversi }}</p>

                            <p class="mt-1 text-sm text-slate-600">
                                {{ $hasil->jumlah_benar }} jawaban benar dari
                                {{ $hasil->total_soal }} soal
                            </p>
                        @endif
                    @endif
                </div>

                @if ($paketTryout !== null)
                    <div class="flex flex-wrap items-center gap-2 md:shrink-0 md:justify-end">
                        @if ($hasil === null)
                            {{--
                                Tombol yang sama dengan halaman daftar tryout,
                                untuk kondisi yang sama pula: `mulai()` mengembalikan
                                percobaan yang sedang berjalan bila sudah ada, jadi
                                peserta yang setengah mengerjakan justru langsung
                                dibawa melanjutkan, bukan diberi pesan galat.
                            --}}
                            <form method="POST" action="{{ route('tryout.mulai', $paketTryout) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Mulai Tryout</button>
                            </form>
                        @else
                            <a href="{{ route('tryout.hasil', $paketTryout) }}" class="btn btn-primary">Lihat hasil</a>
                        @endif

                        <a href="{{ route('tryout.index') }}" class="btn btn-ghost">Semua tryout</a>
                    </div>
                @endif
            </div>
        </section>
    @endif
</x-layouts.app>
