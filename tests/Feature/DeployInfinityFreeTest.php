<?php

use Illuminate\Support\Facades\File;

it('menyiapkan htaccess yang mengarahkan permintaan ke folder public', function () {
    $berkas = base_path('deploy/htdocs.htaccess');

    expect(File::exists($berkas))->toBeTrue();

    $isi = File::get($berkas);

    expect($isi)
        ->toContain('RewriteEngine On')
        ->toContain('RewriteRule ^storage/(.*)$ /storage/app/public/$1')
        ->toContain('RewriteRule (.*) /public/$1');
});

it('menyiapkan contoh env produksi dengan pengaturan server tanpa antrean dan tanpa surel', function () {
    $berkas = base_path('deploy/env.production.example');

    expect(File::exists($berkas))->toBeTrue();

    $isi = File::get($berkas);

    expect($isi)
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('SESSION_DRIVER=database')
        ->toContain('CACHE_STORE=database')
        ->toContain('QUEUE_CONNECTION=sync')
        ->toContain('MAIL_MAILER=log')
        ->toContain('GEMINI_TIMEOUT=')
        ->toContain('GEMINI_MODELS=');
});

it('menyiapkan skrip paket rilis yang memeriksa batas berkas php hosting', function () {
    $berkas = base_path('deploy/release.sh');

    expect(File::exists($berkas))->toBeTrue();

    $isi = File::get($berkas);

    expect($isi)
        ->toContain('--no-dev')
        ->toContain('BATAS_PHP')
        ->toContain('public/build/manifest.json');
});

it('menyiapkan skrip pembuat database produksi yang menyaring data uji', function () {
    $berkas = base_path('deploy/basis-data.sh');

    expect(File::exists($berkas))->toBeTrue();

    $isi = File::get($berkas);

    expect($isi)
        ->toContain('schema:dump')
        ->toContain('mysqldump')
        ->toContain("--where='id=1'")
        ->not->toContain('riwayat_pengerjaan');
});
