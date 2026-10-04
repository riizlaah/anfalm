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

    {{--
        Kartu latihan juga hanya sampai ke peserta, seperti dua blok di atasnya.
        Daftarnya `mapelTerpilih()` — wajib ∪ pilihan yang dipilih — bukan
        `mapelTampil()`: aturan "kosong = semua" akan menumbuhkan sepuluh kartu
        bagi peserta yang belum memilih apa pun, padahal blok ini merangkum yang
        sedang ia kejar. Yang berbeda per kartu adalah level, ajakan, dan label
        tombolnya; tujuannya satu dan sama, halaman latihan. Pill levelnya
        memakai idiom yang sama dengan kode KD di halaman Analisis supaya
        "sekilas baca" terasa di dua tempat.

        Lebarnya `max-w-3xl` seperti kartu streak dan tryout terbaru di atasnya:
        blok ini tadinya `max-w-5xl` dan grid tiga kolom, sehingga tepi kanannya
        menjorok jauh melewati dua kartu di atas — persis asimetris yang dilaporkan.
        Tiga kolom juga mustahil dipertahankan pada lebar itu tanpa memaksa dua
        kartu di atasnya ikut melebar, padahal isinya cuma dua baris teks.
        Dua kolom muat di `3xl` dan menyatukan seluruh halaman pada satu lebar.
    --}}
    @if ($kartuLatihan !== null && $kartuLatihan->isNotEmpty())
        <section class="mt-8 max-w-3xl">
            <h2 class="text-lg font-semibold text-ink">Latihan per mapel</h2>

            <p class="mt-1 text-sm text-slate-600">
                Level tiap mapel berasal dari latihan dan tryoutmu, dan ajakan
                di bawahnya menyesuaikan level itu.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($kartuLatihan as $kartu)
                    {{-- `flex flex-col` + `mt-auto` pada tombolnya: ajakan satu
                         baris dan dua baris menghasilkan tinggi kartu yang sama
                         (grid meregangkannya), tetapi tanpa dorongan itu tombol
                         duduk tepat di bawah teksnya masing-masing dan barisan
                         tombolnya tampak meleset. `mb-4` di paragraf menjaga
                         jarak minimum tetap ada ketika kartu justru penuh. --}}
                    <article class="card flex flex-col p-5" data-kartu-latihan>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="card-title min-w-0">{{ $kartu['mapel']->nama }}</h3>

                            <span class="shrink-0 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-ink">
                                {{ $kartu['label'] }}
                            </span>
                        </div>

                        <p class="mt-2 mb-4 text-sm text-slate-600">{{ $kartu['ajakan'] }}</p>

                        <a href="{{ route('latihan.index') }}" class="btn btn-primary mt-auto w-full">
                            {{ $kartu['tombol'] }}
                        </a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
