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

/**
 * Baris manifest berformat `sha256  ./path`. Hash-nya diturunkan dari isi
 * baris, supaya hash lama dan hash baru bagi path yang sama terlihat berbeda —
 * itulah satu-satunya hal yang membuat `comm` menganggap berkas berubah.
 */
function barisManifest(string $path, string $isi): string
{
    return hash('sha256', $isi).'  ./'.$path;
}

/**
 * Menghapus `hash  ./` sehingga tersisa path murni — sama persis dengan
 * `strip_hash()` di sinkron.sh yang dipakai untuk menghitung berkas usang.
 */
function pathManifest(string $baris): string
{
    return preg_replace('/^[0-9a-f]+  \.\//', '', $baris);
}

/**
 * Menjalankan skrip penggabung sungguhan terhadap empat berkas buatan, lalu
 * mengembalikan isi manifest hasilnya.
 *
 * @param  array<int, string>  $lama
 * @param  array<int, string>  $baru
 * @param  array<int, string>  $terkirim
 * @param  array<int, string>  $terbuang
 */
function jalankanPenggabung(array $lama, array $baru, array $terkirim, array $terbuang, string $hapus): string
{
    $dir = sys_get_temp_dir().'/gabung-manifest-'.uniqid();
    File::ensureDirectoryExists($dir);

    $tulis = function (string $nama, array $baris) use ($dir): string {
        File::put($dir.'/'.$nama, $baris === [] ? '' : implode("\n", $baris)."\n");

        return $dir.'/'.$nama;
    };

    $keluaran = $dir.'/keluaran.txt';

    $hasil = shell_exec(
        'bash '.escapeshellarg(base_path('deploy/gabung-manifest.sh'))
        .' '.escapeshellarg($tulis('lama.txt', $lama))
        .' '.escapeshellarg($tulis('baru.txt', $baru))
        .' '.escapeshellarg($tulis('terkirim.txt', $terkirim))
        .' '.escapeshellarg($tulis('terbuang.txt', $terbuang))
        .' '.escapeshellarg($hapus)
        .' '.escapeshellarg($keluaran)
        .' 2>&1; echo "KODE=$?"'
    ) ?? '';

    expect($hasil)->toContain('KODE=0');

    $isi = File::get($keluaran);
    File::deleteDirectory($dir);

    return $isi;
}

/**
 * Menirukan `comm -13 lama baru` di sinkron.sh: baris yang hanya ada pada
 * manifest keinginan — berkas baru atau berubah, sehingga wajib diunggah.
 *
 * @return array<int, string>
 */
function daftarUnggahBerikutnya(string $hasil, string $baru): array
{
    return array_values(array_diff(
        array_values(array_filter(explode("\n", trim($baru)))),
        array_values(array_filter(explode("\n", trim($hasil)))),
    ));
}

/**
 * Menirukan `comm -23 strip(lama) strip(baru)`: path yang ada di jejak server
 * tetapi tidak lagi ada di paket — berkas usang yang harus dibuang.
 *
 * @return array<int, string>
 */
function daftarHapusBerikutnya(string $hasil, string $baru): array
{
    $path = fn (string $manifest): array => array_values(array_filter(array_map(
        'pathManifest',
        explode("\n", trim($manifest)),
    )));

    return array_values(array_diff($path($hasil), $path($baru)));
}

it('mencatat keadaan server yang sesungguhnya walau ada berkas gagal diunggah', function () {
    $hasil = jalankanPenggabung(
        [barisManifest('a.php', 'isi lama'), barisManifest('b.php', 'isi lama')],
        [barisManifest('a.php', 'isi baru'), barisManifest('b.php', 'isi baru')],
        ['a.php'],       // hanya a.php yang berhasil
        [],              // tidak ada yang dibuang
        'tidak',
    );

    // Kalau manifest ditulis apa adanya seperti keinginan, b.php dianggap
    // sudah ada di server dan tidak akan pernah diunggah ulang.
    expect($hasil)
        ->toContain(barisManifest('a.php', 'isi baru'))
        ->toContain(barisManifest('b.php', 'isi lama'));
});

it('menyebut kembali hanya berkas yang gagal pada sinkron berikutnya', function () {
    $baru = implode("\n", [barisManifest('a.php', 'isi baru'), barisManifest('b.php', 'isi baru')])."\n";

    $hasil = jalankanPenggabung(
        [barisManifest('a.php', 'isi lama'), barisManifest('b.php', 'isi lama')],
        array_filter(explode("\n", trim($baru))),
        ['a.php'],
        [],
        'tidak',
    );

    // Inilah yang selama ini mustahil: kegagalan dua berkas tidak pernah
    // memaksa 6455 berkas diunggah ulang.
    expect(daftarUnggahBerikutnya($hasil, $baru))
        ->toBe([barisManifest('b.php', 'isi baru')])
        ->and(daftarHapusBerikutnya($hasil, $baru))->toBe([]);
});

it('tidak mencatat berkas baru yang belum terkirim sama sekali', function () {
    $baru = [barisManifest('ada.php', 'isi'), barisManifest('baru.php', 'isi')];

    $hasil = jalankanPenggabung([], $baru, ['ada.php'], [], 'tidak');

    // Mencatatnya akan menandai berkas yang tak pernah sampai ke server sebagai
    // sudah ada, sehingga server kehilangan berkas itu selamanya.
    expect($hasil)
        ->toContain(barisManifest('ada.php', 'isi'))
        ->not->toContain(barisManifest('baru.php', 'isi'));
});

it('menyusun ulang jejak run pertama yang gagal sebagian', function () {
    $baru = [
        barisManifest('satu.php', 'isi'),
        barisManifest('dua.php', 'isi'),
        barisManifest('tiga.php', 'isi'),
    ];

    // Belum pernah ada manifest lama: seluruh isi paket diunggah, dua gagal.
    $hasil = jalankanPenggabung([], $baru, ['satu.php'], [], 'tidak');

    expect(daftarUnggahBerikutnya($hasil, implode("\n", $baru)."\n"))
        ->toHaveCount(2)
        ->not->toContain(barisManifest('satu.php', 'isi'));
});

it('mempertahankan berkas usang yang gagal dibuang', function () {
    $lama = [barisManifest('usang.php', 'isi lama')];
    $baru = [barisManifest('baru.php', 'isi')];

    $hasil = jalankanPenggabung($lama, $baru, ['baru.php'], [], 'ya');

    expect(daftarHapusBerikutnya($hasil, implode("\n", $baru)."\n"))
        ->toBe(['usang.php']);
});

it('mempertahankan berkas usang bila fase hapus tidak dijalankan sama sekali', function () {
    // `--bersih` tidak dijalankan, sehingga berkas usang masih ada di server.
    // Bila jejaknya tetap ditulis seperti keinginan, `--bersih` pada run
    // berikutnya tak akan lagi menemukannya dan berkas itu abadi di server.
    $lama = [barisManifest('usang.php', 'isi lama')];
    $baru = [barisManifest('baru.php', 'isi')];

    $hasil = jalankanPenggabung($lama, $baru, ['baru.php'], [], 'tidak');

    expect(daftarHapusBerikutnya($hasil, implode("\n", $baru)."\n"))
        ->toBe(['usang.php']);
});

it('menghapus berkas usang yang berhasil dibuang dari jejak', function () {
    $lama = [barisManifest('usang.php', 'isi lama')];
    $baru = [barisManifest('baru.php', 'isi')];

    $hasil = jalankanPenggabung($lama, $baru, ['baru.php'], ['usang.php'], 'ya');

    expect(daftarHapusBerikutnya($hasil, implode("\n", $baru)."\n"))->toBe([]);
});

it('menghasilkan keadaan yang diinginkan bila tidak ada kegagalan', function () {
    $lama = [
        barisManifest('tetap.php', 'sama'),
        barisManifest('berubah.php', 'lama'),
        barisManifest('usang.php', 'lama'),
    ];
    $baru = [
        barisManifest('tetap.php', 'sama'),
        barisManifest('berubah.php', 'baru'),
        barisManifest('baru.php', 'baru'),
    ];

    $hasil = jalankanPenggabung($lama, $baru, ['berubah.php', 'baru.php'], ['usang.php'], 'ya');

    // Perilaku lama tidak berubah sedikit pun kalau semuanya bersih.
    expect(array_values(array_filter(explode("\n", trim($hasil)))))
        ->toEqualCanonicalizing(array_values(array_filter(explode("\n", trim(implode("\n", $baru)."\n")))));
});

it('selalu mengeluarkan manifest yang terurut menurut kolasi C', function () {
    // sinkron.sh memeriksa `LC_ALL=C sort -c` sebelum memakai jejaknya. Manifest
    // yang diurut di bawah kolasi lain akan ditolak pemeriksaan itu, dan pesan
    // pemulihannya meminta file dihapus — full unggah, kebalikan dari tujuannya.
    $isi = File::get(base_path('deploy/gabung-manifest.sh'));

    expect($isi)->toContain('LC_ALL=C');
});

it('sinkron.sh memanggil penggabung dan tidak lagi menyalin manifest keinginan', function () {
    expect(skripSinkron())
        ->toContain('gabung-manifest.sh')
        ->not->toContain('cp "$MANIFEST_BARU" "$MANIFEST_LAMA"');
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

it('memaksa TLS, IPv4, dan PASV pada setiap sesi', function () {
    // Ketiganya ditulis ke berkas konfigurasi, bukan di baris perintah, supaya
    // tidak mungkin ada satu pun pemanggilan curl yang lupa. Percobaan di
    // server nyata membuktikan mengapa: alamat IPv6 milik ftpupload.net
    // dijawab `500 Unknown command` atas EPSV, dan di jalur itu curl keluar
    // dengan kode 8 tanpa sempat jatuh ke PASV — sementara di IPv4 ia jatuh ke
    // PASV dengan sendirinya.
    expect(skripSinkron())
        ->toContain('ssl-reqd')
        ->toContain('ipv4')
        ->toContain('disable-epsv')
        ->toContain('chmod 600');
});

it('mengecek koneksi sebelum merakit paket', function () {
    // Bila koneksi mati, skrip harus berhenti sebelum `release.sh` membangun
    // zip dan memindai seluruh vendor — biayanya nol berkas tersentuh.
    $ftp = sys_get_temp_dir().'/ftp-mati-'.uniqid().'.ini';
    file_put_contents($ftp, implode("\n", [
        'HOST=127.0.0.1',
        'PENGGUNA=uji',
        'SANDI=rahasiaUjiTidakNyata',
        'REMOTE=/htdocs',
        'PORT=1',
        '',
    ]));

    try {
        $hasil = shell_exec(
            'cd '.escapeshellarg(base_path())
            .' && FTP_INI='.escapeshellarg($ftp).' bash deploy/sinkron.sh 2>&1; echo "KODE=$?"'
        );
    } finally {
        @unlink($ftp);
    }

    expect($hasil)
        ->toContain('KODE=1')
        ->toContain('gagal menyambung')
        ->not->toContain('Merakit paket rilis')
        // Fase unggah tidak pernah tercapai, jadi pesan suksesnya tidak boleh
        // ikut tercetak — kalau tidak, "Berhasil mengunggah" bisa muncul pada
        // run yang bermasalah dan dipercaya orang sebagai tanda tuntas.
        ->not->toContain('Berhasil mengunggah')
        ->not->toContain('Selesai dalam');
});

it('menyebut penyebab sebenarnya sesuai kode keluar curl', function () {
    // Kode 8 (respons server tidak wajar pada koneksi data) pernah ikut
    // dilaporkan sebagai "kredensial atau akses ditolak", yang mengarahkan
    // penyelidikan ke arah yang salah justru saat kredensialnya benar.
    expect(skripSinkron())
        ->toContain('respons server tidak wajar')
        ->toContain('kredensial ditolak server')
        ->not->toContain('kredensial atau akses ditolak');
});

it('mode uji membuktikan TLS benar-benar dinegosiasikan', function () {
    expect(skripSinkron())
        ->toContain('--uji')
        ->toContain('AUTH TLS')
        ->toContain('234');
});

it('menampilkan satu baris kemajuan selama berkas diunggah', function () {
    // Tanpa ini, sinkron penuh berjalan tanpa satu pun keluaran baru setelah
    // `== Mengunggah ==` —6455 unggah lama kelar dan skrip terbaca seperti
    // macet. Pengawasnya berjalan di latar dan harus ikut mati pada setiap
    // jalan keluar, termasuk kegagalan di tengah jalan dan Ctrl-C.
    expect(skripSinkron())
        ->toContain('pengawas_kemajuan')
        ->toContain('\r')
        ->toContain('$PENGAWAS');
});

it('mencatat tiap berkas terkirim supaya kemajuan bisa ditelusuri', function () {
    // Berkas hitung kemajuan sekaligus catatan: bila skrip berhenti di tengah
    // jalan, baris terakhirnya menyebut berkas mana yang sedang dikirim.
    expect(skripSinkron())
        ->toContain('unggah.log')
        ->toContain('buang.log');
});

it('menyebut keberhasilan tiap fase beserta jumlahnya', function () {
    // `Selesai.` saja tidak menjawab apa pun: ia tercetak setelah kedua fase
    // yang sama-sama senyap, sehingga peserta run tidak tahu apa yang betul-
    // betul dikerjakan atau berapa banyak yang terkirim.
    expect(skripSinkron())
        ->toContain('Berhasil mengunggah')
        ->toContain('Berhasil membuang')
        ->toContain('Tidak ada berkas yang berubah')
        ->toContain('Selesai dalam');
});

it('menyebut berhasil hanya bila seluruh berkas benar-benar terkirim', function () {
    // Pembandingnya harus mendahului pesan sukses. Bila urutannya terbalik,
    // `Berhasil mengunggah` tercetak lebih dulu lalu disusul daftar kegagalan —
    // dua pernyataan yang saling membantah pada layar yang sama.
    $pembandingUnggah = strpos(skripSinkron(), '[ "$n_terkirim" -eq "$n_unggah" ]');
    $pembandingBuang = strpos(skripSinkron(), '[ "$n_dibuang" -eq "$n_hapus" ]');

    expect($pembandingUnggah)->not->toBeFalse()
        ->and($pembandingBuang)->not->toBeFalse()
        ->and($pembandingUnggah)->toBeLessThan(strpos(skripSinkron(), 'Berhasil mengunggah'))
        ->and($pembandingBuang)->toBeLessThan(strpos(skripSinkron(), 'Berhasil membuang'));
});

it('kegagalan unggah ikut terlihat di layar, bukan hanya di berkas', function () {
    // Sebelumnya kegagalan hanya diam-diam masuk ke GAGAL-unggah.txt, jadi
    // skrip bisa keluar dengan layar yang bersih dan run tetap gagal.
    expect(skripSinkron())
        ->toContain('gagal:')
        ->toContain('DAFTAR_GAGAL');
});
