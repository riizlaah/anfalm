<x-layouts.app title="Hasil Latihan">
    @php
        $total = $riwayat->count();
        $kosong = $riwayat->filter(fn ($baris) => $baris->skor_irt === null)->count();
        $salah = $total - $jumlahBenar - $kosong;
    @endphp

    <h1 class="page-title">Hasil Latihan</h1>

    <p class="mt-1 text-sm text-slate-600">{{ $mapel?->nama }} · {{ $total }} soal</p>

    <x-alert />

    <section class="card mt-5 p-6">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-slate-500">Jawaban benar</dt>
                <dd class="text-lg font-semibold text-ink">{{ $jumlahBenar }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Jawaban salah</dt>
                <dd class="text-lg font-semibold text-ink">{{ $salah }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Tidak dijawab</dt>
                <dd class="text-lg font-semibold text-ink">{{ $kosong }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Durasi</dt>
                <dd class="text-lg font-semibold text-ink">
                    {{ floor(($percobaan->durasi_detik ?? 0) / 60) }} menit
                </dd>
            </div>
        </dl>
    </section>

    <x-perkembangan-kd :baris="$perKd" />

    <x-pembahasan-soal :riwayat="$riwayat" />

    <div class="mt-6 flex flex-wrap justify-end gap-2">
        <a href="{{ route('latihan.index') }}" class="btn btn-ghost">Latihan lagi</a>
        <a href="{{ route('tryout.index') }}" class="btn btn-ghost">Ke daftar tryout</a>
    </div>
</x-layouts.app>
