{{--
    Render satu isi konten soal (pertanyaan, opsi jawaban, atau pembahasan) secara aman.

    Dua wujud konten ditangani berbeda: HTML hasil editor disaring dulu lalu dirender
    mentah, sedangkan teks biasa di-escape dan pergantian barisnya dijadikan <br />
    sehingga tampil sama persis dengan perilaku nl2br(e(...)) yang lama.

    @param string|null $isi
--}}

@props(['isi'])

{!! app(\App\Domain\Konten\KontenSanitizer::class)->untukTampilan($isi) !!}
