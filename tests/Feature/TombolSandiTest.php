<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/**
 * Saran pertama: tombol toggle show password di password input.
 *
 * Tombolnya dibangun di dalam `<x-input>` supaya tidak mungkin ada form sandi
 * yang lupa dipasangi — semua delapan input sandi di aplikasi melewati satu
 * komponen itu.
 */
it('input sandi selalu ditemani tombol untuk memperlihatkan isinya', function () {
    $html = Blade::render('<x-input label="Kata Sandi" name="password" type="password" required />');

    expect($html)
        ->toContain('type="password"')
        ->toContain('data-tombol-sandi')
        ->toContain('type="button"')
        ->toContain('aria-label="Tampilkan sandi"')
        ->toContain('aria-pressed="false"')
        // Label harus memilih input lewat `for`, bukan membungkusnya: kalau
        // tombol ikut berada di dalam `<label>`, teks "Tampilkan sandi" masuk
        // ke nama aksesibel input.
        ->toContain('for="input-password"')
        ->toContain('id="input-password"');
});

it('tombol toggle sandi berdiri di luar label inputnya', function () {
    $html = Blade::render('<x-input label="Kata Sandi" name="password" type="password" required />');

    $tutupLabel = strpos($html, '</label>');
    $tombol = strpos($html, 'data-tombol-sandi');

    expect($tutupLabel)->not->toBeFalse();
    expect($tombol)->not->toBeFalse();
    expect($tutupLabel)->toBeLessThan($tombol);
});

it('input selain sandi tidak diberi tombol toggle', function () {
    $html = Blade::render('<x-input label="Nama Lengkap" name="nama_lengkap" type="text" />');

    expect($html)->not->toContain('data-tombol-sandi');
});

it('halaman login memuat satu tombol toggle sandi', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect(substr_count($html, 'data-tombol-sandi'))->toBe(1);
});

it('halaman pendaftaran memuat dua tombol toggle sandi', function () {
    $html = $this->get('/register')->assertOk()->getContent();

    expect(substr_count($html, 'data-tombol-sandi'))->toBe(2);
});

it('halaman profil memuat tiga tombol toggle pada form ganti sandi', function () {
    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)->get(route('profil.show'))->assertOk()->getContent();

    expect(substr_count($html, 'data-tombol-sandi'))->toBe(3);
});

it('halaman edit pengguna memuat dua tombol toggle sandi', function () {
    $admin = User::factory()->admin()->create();
    $pengguna = User::factory()->peserta()->create();

    $html = $this->actingAs($admin)
        ->get(route('admin.user.edit', $pengguna))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-tombol-sandi'))->toBe(2);
});

it('berkas js memuat penangan klik tombol toggle sandi', function () {
    expect(File::get(resource_path('js/app.js')))->toContain('data-tombol-sandi');
});
