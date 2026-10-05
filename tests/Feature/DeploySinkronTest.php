<?php

use Illuminate\Support\Facades\File;

/**
 * Jalur update utama: curl mengunggah hanya berkas yang berubah dengan TLS
 * dipaksa, sementara `release.sh` tetap merakit zip sebagai cadangan bila FTP
 * sedang bermasalah.
 *
 * lftp awalnya dipakai, tetapi build di mesin ini tidak pernah mengirim
 * `AUTH TLS` walaupun `ftp:ssl-force` sudah dinyalakan — kata sandi akan
 * terkirim polos. Pengujian di bawah mengunci fakta itu agar tidak kembali.
 *
 * Seluruh pengujian membatasi diri pada berkas skrip — tidak ada koneksi
 * jaringan — supaya cepat dan tidak pernah menyentuh server sungguhan.
 */
function skripSinkron(): string
{
    return File::get(base_path('deploy/sinkron.sh'));
}

it('skrip sinkron tersedia dan dapat dieksekusi', function () {
    expect(File::exists(base_path('deploy/sinkron.sh')))->toBeTrue()
        ->and(is_executable(base_path('deploy/sinkron.sh')))->toBeTrue();
});

it('menyediakan templat kredensial ftp tanpa memasukkannya ke git', function () {
    $templat = File::get(base_path('deploy/ftp.example.ini'));

    foreach (['HOST=', 'PENGGUNA=', 'SANDI=', 'REMOTE='] as $kunci) {
        expect($templat)->toContain($kunci);
    }

    $gitignore = File::get(base_path('.gitignore'));

    expect($gitignore)
        ->toContain('/deploy/ftp.ini')
        ->not->toContain('ftp.example.ini');
});

it('menolak perintah hapus tanpa konfirmasi --ya', function () {
    $hasil = shell_exec(
        'cd '.escapeshellarg(base_path()).' && bash deploy/sinkron.sh --bersih 2>&1; echo "KODE=$?"'
    );

    expect($hasil)
        ->toContain('KODE=1')
        ->toContain('--ya');
});

it('menolak jalan sebelum kredensial diisi', function () {
    $hasil = shell_exec(
        'cd '.escapeshellarg(base_path())
        .' && FTP_INI=/tmp/tidak-ada-ftp.ini bash deploy/sinkron.sh 2>&1; echo "KODE=$?"'
    );

    expect($hasil)
        ->toContain('KODE=1')
        ->toContain('deploy/ftp.example.ini');
});

it('menolak jejak sinkron sebelumnya yang tidak terurut dan menyebut cara memulihkan', function () {
    $build = base_path('deploy/build');
    File::ensureDirectoryExists($build);

    $jejak = $build.'/manifest-akhir.txt';
    $cadangan = File::exists($jejak) ? File::get($jejak) : null;

    // Dua baris sah tetapi terbalik urutannya — persis yang membuat `comm`
    // berhenti di tengah jalan tanpa pesan yang bisa ditindaklanjuti.
    File::put($jejak, implode("\n", [
        str_repeat('f', 64).'  ./b.php',
        str_repeat('0', 64).'  ./a.php',
        '',
    ]));

    $ftp = sys_get_temp_dir().'/ftp-uji-'.uniqid().'.ini';
    file_put_contents($ftp, implode("\n", [
        'HOST=ftp.example.invalid',
        'PENGGUNA=uji',
        'SANDI=rahasiaUjiTidakNyata',
        'REMOTE=/htdocs',
        '',
    ]));

    try {
        $hasil = shell_exec(
            'cd '.escapeshellarg(base_path())
            .' && FTP_INI='.escapeshellarg($ftp).' bash deploy/sinkron.sh 2>&1; echo "KODE=$?"'
        );
    } finally {
        if ($cadangan === null) {
            @unlink($jejak);
        } else {
            File::put($jejak, $cadangan);
        }
        @unlink($ftp);
    }

    expect($hasil)
        ->toContain('KODE=1')
        ->toContain('manifest-akhir.txt')
        ->toContain('Hapus');
});

it('memutuskan perubahan dari checksum lokal, bukan waktu ubah server', function () {
    expect(skripSinkron())
        ->toContain('sha256sum')
        ->toContain('manifest-akhir.txt');
});

it('merakit zip cadangan lewat release.sh', function () {
    expect(skripSinkron())->toContain('deploy/release.sh');
});

it('menyaring daftar hapus agar tak menyentuh kredensial dan unggahan', function () {
    expect(skripSinkron())
        ->toContain('.env')
        ->toContain('storage/app/public/')
        ->toContain('storage/logs/')
        ->toContain('storage/framework/')
        ->toContain('.htaccess');
});

it('kata sandi hanya lewat berkas konfigurasi, bukan baris perintah', function () {
    expect(skripSinkron())
        ->toContain('chmod 600')
        ->toContain('user =')
        ->toContain('curl -K');

    // `ps` akan memperlihatkan apa pun yang ditulis setelah `curl` di baris
    // perintah, jadi `--user` dilarang sama sekali.
    expect(preg_match('/--user\b/', skripSinkron()))->toBe(0);
});

it('memaksa TLS pada setiap sesi', function () {
    expect(skripSinkron())->toContain('--ssl-reqd');
});

it('mode uji membuktikan TLS benar-benar dinegosiasikan', function () {
    expect(skripSinkron())
        ->toContain('--uji')
        ->toContain('AUTH TLS')
        ->toContain('234');
});
