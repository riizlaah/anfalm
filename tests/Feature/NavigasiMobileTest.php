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
    return ['dashboard', 'tryout.index', 'latihan.index', 'analisis.index', 'profil.show'];
}

/**
 * Seluruh tautan yang ada di dalam potongan HTML, dipakai untuk memastikan
 * setiap menu — bukan sekadar tab bar secara keseluruhan — membawa ikonnya.
 *
 * @return array<int, string>
 */
function potonganTautan(string $html): array
{
    preg_match_all('/<a\s[^>]*>.*?<\/a>/s', $html, $cocok);

    return $cocok[0];
}

/**
 * Ikon dianggap sah bila memuat viewBox, penanda aksesibilitas, dan setidaknya
 * satu elemen goresan — menangkap ikon yang sengaja dikosongkan atau lupa
 * diberi `aria-hidden`.
 */
function punyaIkonSah(string $tautan): bool
{
    $adaGoresan = str_contains($tautan, '<path') || str_contains($tautan, '<rect');

    return str_contains($tautan, '<svg')
        && str_contains($tautan, 'viewBox="0 0 24 24"')
        && str_contains($tautan, 'aria-hidden="true"')
        && $adaGoresan;
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
        ->toContain(route('admin.paket-tryout.index'))
        ->toContain(route('admin.user.index'));
});

it('setiap menu di tab bar bawah memuat ikon SVG inline yang sah', function () {
    $peserta = User::factory()->peserta()->create();

    $tabBar = potonganTabBar(
        $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent()
    );

    $tautan = potonganTautan($tabBar);

    expect($tautan)->toHaveCount(count(menuPeserta()));

    foreach ($tautan as $linkMenu) {
        expect(punyaIkonSah($linkMenu))->toBeTrue();
    }
});

it('admin tidak melihat menu Latihan dan Analisis', function () {
    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

    foreach ([potonganTabBar($html), potonganNavDesktop($html)] as $potongan) {
        expect($potongan)
            ->not->toBe('')
            ->not->toContain(route('latihan.index'))
            ->not->toContain(route('analisis.index'));
    }
});

it('peserta tetap melihat menu Latihan dan Analisis', function () {
    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)->get(route('dashboard'))->assertOk()->getContent();

    foreach ([potonganTabBar($html), potonganNavDesktop($html)] as $potongan) {
        expect($potongan)
            ->not->toBe('')
            ->toContain(route('latihan.index'))
            ->toContain(route('analisis.index'));
    }
});

it('tab bar admin memuat ikon pada seluruh menu', function () {
    $admin = User::factory()->admin()->create();

    $tabBar = potonganTabBar(
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent()
    );

    $tautan = potonganTautan($tabBar);

    // Dashboard, Tryout, Mapel, Paket Soal, Paket Tryout, Pengguna, Profil.
    expect($tautan)->toHaveCount(7);

    foreach ($tautan as $linkMenu) {
        expect(punyaIkonSah($linkMenu))->toBeTrue();
    }
});

it('ikon tidak bocor ke navigasi desktop', function () {
    $peserta = User::factory()->peserta()->create();

    $navDesktop = potonganNavDesktop(
        $this->actingAs($peserta)->get(route('tryout.index'))->assertOk()->getContent()
    );

    expect($navDesktop)->not->toBe('')
        ->and($navDesktop)->not->toContain('<svg');
});
