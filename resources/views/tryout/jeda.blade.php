{{--
    Jeda antar mapel.

    Halaman ini sengaja berisi informasi mapel berikutnya saja — tanpa skor,
    tanpa analisis. Hasil diletakkan di akhir percobaan (DESIGN §3.7 langkah
    12); angka di tengah jalan justru bisa mengubah cara peserta mengerjakan
    mapel yang belum dibuka.

    Hitung mundur juga tidak dijalankan di sini. `batas_waktu_menit` memang
    sudah menunjuk mapel berikutnya, tetapi `mulai_mapel` baru disetel ketika
    tombol di bawah ditekan, sehingga istirahat tidak memakan jatah waktu
    pengerjaan dan jeda boleh sepanjang yang peserta mau.
--}}
<x-layouts.app title="Jeda antar Mapel" :tabbar="false">
    <h1 class="page-title">Jeda antar Mapel</h1>

    <div class="card mt-5 max-w-3xl space-y-5 p-6">
        <p class="text-sm text-slate-600">
            {{ $paketTryout->nama_paket }} ·
            Mapel {{ $posisiBerikut - 1 }} dari {{ $totalMapel }} selesai ·
            Tingkat {{ $paketTryout->labelTingkat() }}
        </p>

        <div>
            <p class="label">Mapel berikutnya</p>

            <p class="text-lg font-semibold text-ink">{{ $mapelBerikut->nama }}</p>

            <p class="mt-1 text-sm text-slate-600">
                Mapel {{ $posisiBerikut }} dari {{ $totalMapel }}
                · {{ $jumlahSoal }} soal
                · {{ $percobaan->batas_waktu_menit }} menit
            </p>
        </div>

        <p class="hint">
            Hitung mundur mulai berjalan setelah Anda menekan tombol di bawah —
            waktu istirahat ini tidak dihitung.
        </p>

        <div class="pt-1">
            <form method="POST" action="{{ route('tryout.mulai-mapel', $paketTryout) }}">
                @csrf
                <x-button>Mulai Mapel Berikutnya</x-button>
            </form>
        </div>
    </div>
</x-layouts.app>
