<x-layouts.app :title="'Hasil '.$paketTryout->nama_paket">
    <h1 class="page-title">Hasil Tryout</h1>

    <p class="mt-1 text-sm text-slate-600">{{ $paketTryout->nama_paket }}</p>

    <x-alert />

    <section class="card mt-5 p-6">
        <p class="text-sm font-medium text-slate-500">Skor</p>
        <p class="mt-1 text-4xl font-bold text-ink">{{ $hasil->skor_konversi }}</p>

        <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-slate-500">Theta (IRT)</dt>
                <dd class="text-lg font-semibold text-ink">{{ number_format((float) $hasil->theta_final, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Jawaban benar</dt>
                <dd class="text-lg font-semibold text-ink">{{ $hasil->jumlah_benar }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Jawaban salah</dt>
                <dd class="text-lg font-semibold text-ink">{{ $hasil->jumlah_salah }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Soal terjawab</dt>
                <dd class="text-lg font-semibold text-ink">{{ $hasil->total_soal }}/{{ $jumlahSoal }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Skor IRT total</dt>
                <dd class="text-lg font-semibold text-ink">{{ number_format((float) $hasil->skor_irt_total, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Galat baku</dt>
                <dd class="text-lg font-semibold text-ink">
                    {{ $hasil->standard_error !== null ? number_format((float) $hasil->standard_error, 2) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Durasi</dt>
                <dd class="text-lg font-semibold text-ink">{{ floor(($hasil->durasi_total ?? 0) / 60) }} menit</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Selesai pada</dt>
                <dd class="text-lg font-semibold text-ink">{{ $hasil->selesai_pada->format('d M Y, H:i') }}</dd>
            </div>
        </dl>
    </section>

    <x-perkembangan-kd :baris="$perKd" />

    <x-pembahasan-soal :riwayat="$riwayat" />

    <div class="mt-6 flex justify-end">
        <a href="{{ route('tryout.index') }}" class="btn btn-ghost">Kembali ke daftar tryout</a>
    </div>
</x-layouts.app>
