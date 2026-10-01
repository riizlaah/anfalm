<?php

use App\Models\Mapel;
use App\Models\User;

/**
 * Potongan atribut `<body>` yang hanya muncul ketika proteksi 7.10 aktif.
 */
function penandaProteksi(): string
{
    return 'class="flex min-h-full flex-col terlindungi"';
}

it('melindungi halaman peserta dari seleksi, klik kanan, dan salin', function () {
    $this->actingAs(User::factory()->peserta()->create())
        ->get(route('analisis.index'))
        ->assertOk()
        ->assertSee(penandaProteksi(), false);
});

it('tidak menerapkan proteksi pada editor admin', function () {
    $mapel = Mapel::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.mapel.soal.create', $mapel))
        ->assertOk()
        ->assertDontSee(penandaProteksi(), false);
});
