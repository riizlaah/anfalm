@props([
    'label' => '',
    'name' => '',
    'type' => 'text',
    'value' => null,
])

@php
    /**
     * Input sandi selalu memakai `type="password"`, sehingga tombolnya dibangun
     * di sini — form sandi mana pun tidak mungkin lupa dipasanginya.
     *
     * Teks label sengaja dipisah menjadi elemen `<label for>` sendiri dan tombol
     * berdiri di luar `<label>`. Bila keduanya digabung, teks "Tampilkan sandi"
     * ikut terhitung sebagai nama aksesibel input, dan pengguna pembaca layar
     * mendengar "Kata Sandi Tampilkan sandi" untuk tiap kolom sandi.
     *
     * @var string
     */
    $id = $attributes->get('id') ?? 'input-'.$name;

    $sandi = $type === 'password';
@endphp

<div class="block">
    @if ($label)
        <label class="label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <span class="relative block">
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ $sandi ? '' : old($name, $value) }}"
            {{ $attributes->except('id')->merge(['class' => $sandi ? 'input pr-11' : 'input']) }}
        >

        {{-- Karena berada di luar `<label>`, klik tombol ini tidak pernah ikut
             mengaktifkan label; fokusnya tetap pada tombol sehingga pengguna
             keyboard dapat membuka-menutup tampilan berulang kali. --}}
        @if ($sandi)
            <button type="button" data-tombol-sandi
                class="absolute inset-y-0 right-0 flex items-center rounded-lg px-3 text-slate-400 transition hover:text-slate-600 focus:ring-2 focus:ring-ink/25 focus:outline-none"
                aria-label="Tampilkan sandi" aria-pressed="false">
                <x-icon name="mata" class="h-5 w-5" data-sandi="lihat" />
                <x-icon name="mata-tertutup" class="h-5 w-5 hidden" data-sandi="tutup" />
            </button>
        @endif
    </span>
</div>
