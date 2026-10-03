{{--
    Tabel matriks untuk soal PG Kategori: satu baris per pernyataan, satu kolom
    per kategori (mis. Benar / Salah), sesuai bentuk soal PG Kategori pada umumnya.

    Mode jawab merender radio. Nama field persis sama dengan bentuk lama
    (`jawaban[kategori][soal][pernyataan]`), jadi penyimpanan tidak berubah —
    hanya tampilannya yang lebih ringkas dan tidak memakan ruang vertikal
    sebanyak satu <select> di bawah tiap pernyataan.

    Mode tinjau menandai jawaban peserta (●) dan kunci jawaban (✓) pada sel yang
    sama sehingga baris yang salah langsung terlihat.

    @param \App\Models\Soal $soal
    @param array<array-key, mixed> $jawaban  jawaban per pernyataan
    @param bool $tinjau  true untuk halaman hasil/pembahasan
--}}
@props(['soal', 'jawaban' => [], 'tinjau' => false])

@php
    $kategoriList = array_values((array) $soal->daftar_kategori);
@endphp

<div class="mt-4">
    <table class="matriks-kategori w-full border-collapse text-sm">
        <thead>
            <tr class="text-slate-500">
                <th scope="col" class="w-8 border-b-2 border-slate-400 px-2 py-2 text-left font-semibold">#</th>
                <th scope="col" class="border-b-2 border-slate-400 px-2 py-2 text-left font-semibold">Pernyataan</th>

                @foreach ($kategoriList as $kategori)
                    <th scope="col" class="border-b-2 border-slate-400 px-2 py-2 text-center font-semibold">{{ $kategori }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($soal->pernyataanKategori as $index => $pernyataan)
                <tr class="align-top">
                    <td class="border-b border-slate-200 px-2 py-2 text-slate-500">{{ chr(65 + ($index % 26)) }}.</td>

                    <td class="border-b border-slate-200 px-2 py-2">
                        <div class="text-slate-700" data-rumus>
                            <x-konten :isi="$pernyataan->teks_pernyataan" />
                        </div>
                    </td>

                    @foreach ($kategoriList as $kategori)
                        <td class="border-b border-slate-200 px-1 py-1 text-center">
                            @if ($tinjau)
                                @if (($jawaban[$pernyataan->id] ?? null) === $kategori)
                                    <span data-jawabanmu aria-label="Jawabanmu" class="text-base leading-none text-slate-800">●</span>
                                @endif

                                @if ($pernyataan->kategori_benar === $kategori)
                                    <span data-kunci aria-label="Kunci jawaban" class="ml-1 text-base leading-none font-semibold text-emerald-700">✓</span>
                                @endif
                            @else
                                {{-- Label membungkus seluruh sel supaya target sentuh
                                     di HP tidak berhenti di 20px. --}}
                                <label class="flex h-10 cursor-pointer items-center justify-center">
                                    <input type="radio"
                                        name="jawaban[kategori][{{ $soal->id }}][{{ $pernyataan->id }}]"
                                        value="{{ $kategori }}"
                                        class="h-5 w-5 accent-ink"
                                        @checked(($jawaban[$pernyataan->id] ?? null) === $kategori)>
                                </label>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
