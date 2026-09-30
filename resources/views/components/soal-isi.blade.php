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

{{-- Konten soal masih teks polos; penyuntingan HTML dan sanitasinya
     menyusul bersama editor pada Fase 8. --}}
<div class="text-sm leading-relaxed text-slate-800">
    {!! nl2br(e($soal->pertanyaan)) !!}
</div>

@if ($soal->isPGKategori())
    <div class="mt-4 space-y-3">
        @foreach ($soal->pernyataanKategori as $pernyataan)
            <div>
                <p class="text-sm text-slate-700">{{ $pernyataan->teks_pernyataan }}</p>

                <select name="jawaban[kategori][{{ $soal->id }}][{{ $pernyataan->id }}]"
                    class="select mt-1 w-auto">
                    <option value="">Pilih kategori…</option>
                    @foreach ((array) $soal->daftar_kategori as $kategori)
                        <option value="{{ $kategori }}"
                            @selected(($jawabanSoal[$pernyataan->id] ?? null) === $kategori)>
                            {{ $kategori }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>
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
                <span>{{ $opsi->teks_opsi }}</span>
            </label>
        @endforeach
    </div>
@endif
