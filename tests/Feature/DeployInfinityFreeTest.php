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
        ->toContain('public/build/manifest.json')
        ->toContain('deploy/staging.sh')
        ->toContain('env.production.example');
});

/**
 * Pohon uji kecil yang memuat kedua ujung yang harus dibedakan: berkas yang
 * wajib sampai ke server, dan berkas yang hanya berguna di mesin pengembang.
 *
 * @return array{0: string, 1: string} [sumber, tujuan]
 */
function pohonUjiStaging(): array
{
    $sumber = sys_get_temp_dir().'/staging-sumber-'.uniqid();
    $tujuan = sys_get_temp_dir().'/staging-tujuan-'.uniqid();

    $berkas = [
        // Wajib sampai ke server.
        'app/Http/Kernel.php' => '<?php',
        'resources/views/welcome.blade.php' => '<p>halo</p>',
        'public/build/manifest.json' => '{}',

        // Kunci perbaikannya: direktori runtime harus berisi paling tidak
        // satu berkas. Direktori kosong di zip sering dilewati File Manager
        // saat ekstraksi, lalu Laravel menolak menulis ke folder yang tidak
        // ada — persis cacat yang dilaporkan dari paket sebelumnya.
        'storage/framework/cache/.gitignore' => "*\n!.gitignore\n",
        'storage/framework/sessions/.gitignore' => "*\n!.gitignore\n",
        'storage/framework/views/.gitignore' => "*\n!.gitignore\n",
        'storage/logs/.gitignore' => "*\n!.gitignore\n",

        // Sisa runtime lama: harus ikut terbuang.
        'storage/logs/laravel.log' => 'log lama',
        'storage/framework/views/compiled-lama.php' => '<?php',
        'storage/framework/cache/data/capaian.php' => 'x',
        'storage/framework/testing/disks/gambar.png' => 'PNG',

        // Bukan urusan server.
        'AGENTS.md' => '# panduan',
        'DESIGN.md' => '# rancangan',
        'REPORT_N_SUGGEST.md' => '# catatan',
        'opencode.json' => '{}',
        'boost.json' => '{}',
        'package.json' => '{}',
        'package-lock.json' => '{}',
        'vite.config.js' => 'export default {}',
        'phpunit.xml' => '<phpunit/>',
        'resources/js/app.js' => 'console.log(1)',
        'resources/css/app.css' => 'body {}',
        'database/migrations/2026_01_01_uji.php' => '<?php',
        'tests/Feature/Uji.php' => '<?php',
        '.env' => 'SECRET=1',
        '.git/HEAD' => 'ref: refs/heads/main',
    ];

    foreach ($berkas as $relatif => $isi) {
        File::ensureDirectoryExists(dirname($sumber.'/'.$relatif));
        File::put($sumber.'/'.$relatif, $isi);
    }

    return [$sumber, $tujuan];
}

/**
 * Menjalankan langkah staging sungguhan terhadap pohon uji — tanpa npm,
 * composer, maupun jaringan.
 */
function jalankanStaging(string $sumber, string $tujuan): string
{
    return shell_exec(
        'bash '.escapeshellarg(base_path('deploy/staging.sh'))
        .' '.escapeshellarg($sumber).' '.escapeshellarg($tujuan)
        .' 2>&1; echo "KODE=$?"'
    ) ?? '';
}

it('staging menyisakan folder runtime berisi berkas supaya ekstraksi tidak melewatkannya', function () {
    [$sumber, $tujuan] = pohonUjiStaging();

    try {
        expect(jalankanStaging($sumber, $tujuan))->toContain('KODE=0');

        foreach ([
            'storage/framework/cache/.gitignore',
            'storage/framework/sessions/.gitignore',
            'storage/framework/views/.gitignore',
            'storage/logs/.gitignore',
        ] as $relatif) {
            expect(File::exists($tujuan.'/'.$relatif))
                ->toBeTrue()
                ->and(trim(File::get($tujuan.'/'.$relatif)))->not->toBe('');
        }
    } finally {
        File::deleteDirectory($sumber);
        File::deleteDirectory($tujuan);
    }
});

it('staging membuang sisa runtime lama dan berkas yang bukan urusan server', function () {
    [$sumber, $tujuan] = pohonUjiStaging();

    try {
        expect(jalankanStaging($sumber, $tujuan))->toContain('KODE=0');

        foreach ([
            'storage/logs/laravel.log',
            'storage/framework/views/compiled-lama.php',
            'storage/framework/cache/data/capaian.php',
            'storage/framework/testing/disks/gambar.png',
            'AGENTS.md',
            'DESIGN.md',
            'REPORT_N_SUGGEST.md',
            'opencode.json',
            'boost.json',
            'package.json',
            'package-lock.json',
            'vite.config.js',
            'phpunit.xml',
            'resources/js/app.js',
            'resources/css/app.css',
            'database/migrations/2026_01_01_uji.php',
            'tests/Feature/Uji.php',
            '.env',
            '.git/HEAD',
        ] as $relatif) {
            expect(File::exists($tujuan.'/'.$relatif))->toBeFalse();
        }
    } finally {
        File::deleteDirectory($sumber);
        File::deleteDirectory($tujuan);
    }
});

it('staging tetap menyertakan berkas yang diperlukan server', function () {
    [$sumber, $tujuan] = pohonUjiStaging();

    try {
        expect(jalankanStaging($sumber, $tujuan))->toContain('KODE=0');

        foreach ([
            'app/Http/Kernel.php',
            'resources/views/welcome.blade.php',
            'public/build/manifest.json',
        ] as $relatif) {
            expect(File::exists($tujuan.'/'.$relatif))->toBeTrue();
        }
    } finally {
        File::deleteDirectory($sumber);
        File::deleteDirectory($tujuan);
    }
});

it('staging menolak sumber yang tidak ada daripada merakit paket setengah jadi', function () {
    $hasil = shell_exec(
        'bash '.escapeshellarg(base_path('deploy/staging.sh'))
        .' /tmp/sumber-yang-tidak-ada-'.uniqid().' /tmp/tujuan-staging-'.uniqid()
        .' 2>&1; echo "KODE=$?"'
    ) ?? '';

    expect($hasil)
        ->toContain('KODE=1')
        ->toContain('sumber');
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
