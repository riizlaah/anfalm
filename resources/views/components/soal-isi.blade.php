{{--
    Isi satu soal: pertanyaan beserta kontrol jawabannya.

    Dipakai bersama oleh halaman pengerjaan tryout dan latihan supaya tiga tipe
    soal (PG, PG kompleks, PG kategori) hanya punya satu tempat rendering.

    @param \App\Models\Soal $soal
    @param array<string, array<int, mixed>> $jawaban  seluruh jawaban sementara halaman
--}}
@props(['soal', 'jawaban' => []])

@php
    $kunci = $soal->isPGKategori() ? 'kategori' : 'opsi';
    $jawabanSoal = $jawaban[$kunci][$soal->id] ?? null;
@endphp

<div class="text-sm leading-relaxed text-slate-800" data-rumus>
    <x-konten :isi="$soal->pertanyaan" />
</div>

@if ($soal->isPGKategori())
    {{-- Satu tabel matriks, bukan satu <select> per pernyataan: hemat ruang
         vertikal dan langsung terlihat berapa baris yang belum dijawab. --}}
    <x-matriks-kategori :soal="$soal" :jawaban="$jawabanSoal ?? []" />
@else
    @php
        $opsiTerpilih = (array) $jawabanSoal;
        $satuJawaban = $soal->isPG();
    @endphp

    <div class="mt-4 space-y-2">
        @foreach ($soal->opsiJawaban as $opsi)
            <label class="check">
                <input type="{{ $satuJawaban ? 'radio' : 'checkbox' }}"
                    name="jawaban[opsi][{{ $soal->id }}]{{ $satuJawaban ? '' : '[]' }}"
                    value="{{ $opsi->id }}"
                    @checked(in_array($opsi->id, $opsiTerpilih))>
                <span data-rumus><x-konten :isi="$opsi->teks_opsi" /></span>
            </label>
        @endforeach
    </div>
@endif
