<x-layouts.guest title="Beranda">
    <section class="mx-auto w-full max-w-6xl px-4 pt-16 pb-14 sm:pt-24 sm:pb-20">
        <div class="max-w-2xl">
            <h1 class="text-3xl leading-tight font-extrabold tracking-tight text-ink sm:text-4xl">
                Ukur kemampuan akademik lewat tryout yang lebih terukur.
            </h1>
            <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg">
                Anfalm menghadirkan tryout TKA untuk SD, SMP, SMA, dan SMK dengan skoring berbasis Item Response
                Theory. Skor yang dilaporkan bukan sekadar jumlah benar, melainkan estimasi kemampuan.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Buka Dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary">Daftar Gratis</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost">Masuk</a>
                @endauth
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 bg-white">
        <div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:grid-cols-3">
            <div>
                <h2 class="text-lg font-semibold text-ink">Skoring berbasis IRT</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                    Estimasi kemampuan dihitung dari pola jawaban, lalu disajikan sebagai skor pada skala yang
                    konsisten antarpaket.
                </p>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-ink">Beragam tipe soal</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                    Pilihan ganda, pilihan ganda kompleks, dan pernyataan berkategori menyesuaikan karakteristik
                    tiap mata pelajaran.
                </p>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-ink">Pembahasan tiap soal</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                    Setiap jawaban disertai pembahasan untuk membantu memahami kesalahan dan memperkuat pemahaman
                    materi.
                </p>
            </div>
        </div>
    </section>
</x-layouts.guest>