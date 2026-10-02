<?php

use App\Models\Mapel;
use App\Models\Percobaan;
use App\Models\User;

beforeEach(function () {
    $this->seed();
});

/**
 * Potongan HTML tab bar bawah, kosong bila halaman tidak memuatnya.
 *
 * Dipakai supaya pengujian menyorot isi tab bar sendiri, bukan menu desktop
 * yang kebetulan memuat link yang sama.
 */
function potonganTabBar(string $html): string
{
    return preg_match('/<nav id="tabbar-bawah".*?<\/nav>/s', $html, $cocok) === 1 ? $cocok[0] : '';
}

function potonganNavDesktop(string $html): string
{
    return preg_match('/<nav aria-label="Navigasi utama".*?<\/nav>/s', $html, $cocok) === 1 ? $cocok[0] : '';
}

/**
 * @return array<int, string>
 */
function menuPeserta(): array
{
    return ['dashboard', 'tryout.index', 'latihan.index', 'analisis.index'];
}

it('tab bar bawah memuat seluruh menu peserta di setiap halaman peserta', function () {
    $peserta = User::factory()->peserta()->create();

    foreach ([route('dashboard'), route('tryout.index'), route('latihan.index'), route('analisis.index')] as $url) {
        $tabBar = potonganTabBar($this->actingAs($peserta)->get($url)->assertOk()->getContent());

        expect($tabBar)->not->toBe('');

        foreach (menuPeserta() as $menu) {
            expect($tabBar)->toContain(route($menu));
        }
    }
});

it('navigasi desktop tetap utuh walau tab bar bawah ditambahkan', function () {
    $peserta = User::factory()->peserta()->create();

    $navDesktop = potonganNavDesktop(
        $this->actingAs($peserta)->get(route('tryout.index'))->assertOk()->getContent()
    );

    expect($navDesktop)->not->toBe('');

    foreach (menuPeserta() as $menu) {
        expect($navDesktop)->toContain(route($menu));
    }
});

it('tab bar tidak tampil di halaman pengerjaan soal', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = Mapel::whereNull('deleted_at')->orderBy('id')->first();

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $mapel->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'stopwatch',
    ]);

    $percobaan = Percobaan::sole();

    $html = $this->actingAs($peserta)->get(route('latihan.kerja', $percobaan))->assertOk()->getContent();

    expect(potonganTabBar($html))->toBe('')
        ->and(potonganNavDesktop($html))->not->toBe('');
});

it('tab bar admin memuat menu administrasi', function () {
    $admin = User::factory()->admin()->create();

    $tabBar = potonganTabBar(
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent()
    );

    expect($tabBar)->not->toBe('')
        ->toContain(route('admin.mapel.index'))
        ->toContain(route('admin.paket-soal.index'))
        ->toContain(route('admin.paket-tryout.index'));
});
