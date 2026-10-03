{{--
    Daftar level kompetensi per KD untuk halaman hasil tryout maupun latihan.

    Kartu dalam grid, bukan tabel: lima kolom angka di layar 360px hanya bisa
    dibaca dengan menggulir mendatar, sementara isinya muat apa adanya dalam
    satu kartu (laporan "kurangi penggunaan tabel di UI mobile"; tabel hanya
    dipertahankan untuk PG Kategori).

    @param array<int, array{kd: \App\Models\KompetensiDasar, jumlah: int, benar: int, theta: ?float, level: string, label: string}> $baris
--}}
<section class="card mt-5 p-6">
    <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
        Level kompetensi per KD
    </h2>

    <p class="mt-1 text-xs text-slate-500">
        Jumlah dan benar berasal dari percobaan ini. Theta dan level dihitung dari seluruh
        latihan dan tryout Anda pada kompetensi dasar itu, jadi tidak berubah tiap kali
        mengerjakan satu tryout. Belum pernah terjawab berarti belum teridentifikasi.
    </p>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($baris as $barisKd)
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <span class="inline-block rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-ink">
                    {{ $barisKd['label'] }}
                </span>

                <p class="mt-2 text-sm text-slate-700">{{ $barisKd['kd']->deskripsi }}</p>

                <p class="mt-3 text-xs text-slate-500">
                    {{ $barisKd['jumlah'] }} dikerjakan · {{ $barisKd['benar'] }} benar
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Theta {{ $barisKd['theta'] !== null ? number_format((float) $barisKd['theta'], 3) : '—' }}
                </p>
            </article>
        @empty
            <p class="py-4 text-center text-slate-400 sm:col-span-2 lg:col-span-3">
                Belum ada data kompetensi dasar.
            </p>
        @endforelse
    </div>
</section>
