<?php

use App\Http\Controllers\ProfilController;
use App\Http\Middleware\EnsureSingleSession;
use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Butir 96: pilihan mapel adalah *filter tampilan* — ia menentukan mapel apa
 * yang ditawarkan di Analisis dan form Latihan, tetapi tidak pernah
 * membatasi tryout maupun catatan nilai peserta.
 */
it('pendaftaran mengarahkan peserta baru ke halaman profil untuk memilih mapel', function () {
    $this->post('/register', [
        'nama_lengkap' => 'Ahmad Zaki',
        'email' => 'ahmad@anfalm.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertRedirect(route('profil.show'));
});

it('halaman profil menawarkan seluruh mapel kepada peserta yang belum memilih', function () {
    $mapels = Mapel::factory()->count(3)->create();
    $peserta = User::factory()->peserta()->create();

    $response = $this->actingAs($peserta)->get(route('profil.show'))->assertOk();

    $ditawarkan = $response->viewData('wajib')->merge($response->viewData('pilihan'));

    expect($ditawarkan->pluck('id')->all())
        ->toEqualCanonicalizing($mapels->pluck('id')->all());
});

it('profil menyimpan identitas beserta pilihan mapel peserta', function () {
    [$matematika, $fisika] = Mapel::factory()->pilihanUmum()->count(2)->create();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->put(route('profil.update'), [
        'nama_lengkap' => 'Siti Aminah',
        'sekolah' => 'SMK Negeri 2 Bandung',
        'tingkat' => Mapel::TINGKAT_SMA,
        'jurusan' => 'Teknik Kendaraan Ringan',
        'mapel_pilihan' => [$matematika->getKey(), $fisika->getKey()],
    ])->assertRedirect(route('profil.show'));

    $peserta->refresh();

    expect($peserta->nama_lengkap)->toBe('Siti Aminah')
        ->and($peserta->sekolah)->toBe('SMK Negeri 2 Bandung')
        ->and($peserta->tingkat)->toBe(Mapel::TINGKAT_SMA)
        ->and($peserta->jurusan)->toBe('Teknik Kendaraan Ringan')
        ->and($peserta->mapelPilihan()->pluck('mapel.id')->all())
        ->toEqualCanonicalizing([$matematika->getKey(), $fisika->getKey()]);
});

it('profil menolak pilihan mapel yang tidak ada', function () {
    Mapel::factory()->create();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->put(route('profil.update'), [
        'nama_lengkap' => 'Siti Aminah',
        'mapel_pilihan' => [999999],
    ])->assertSessionHasErrors('mapel_pilihan.0');
});

it('pilihan mapel membatasi daftar pilihan di halaman analisis', function () {
    Mapel::factory()->wajib()->create(['nama' => 'Matematika Wajib']);
    Mapel::factory()->pilihanUmum()->create(['nama' => 'Matematika Pilihan']);
    Mapel::factory()->pilihanUmum()->create(['nama' => 'Fisika Luar Pilihan']);
    $peserta = User::factory()->peserta()->create();
    $peserta->mapelPilihan()->sync([Mapel::query()->where('nama', 'Matematika Pilihan')->sole()->getKey()]);

    $response = $this->actingAs($peserta)->get(route('analisis.index'))->assertOk();

    // Mapel wajib selalu ikut tampil — ia bukan sesuatu yang dipilih.
    expect($response->viewData('mapels')->pluck('nama')->all())
        ->toBe(['Matematika Wajib', 'Matematika Pilihan']);
});

it('tanpa pilihan mapel, analisis tetap menampilkan seluruh mapel', function () {
    $mapels = Mapel::factory()->count(3)->create();
    $peserta = User::factory()->peserta()->create();

    $response = $this->actingAs($peserta)->get(route('analisis.index'))->assertOk();

    expect($response->viewData('mapels')->pluck('id')->all())
        ->toEqualCanonicalizing($mapels->pluck('id')->all());
});

it('pilihan mapel membatasi daftar pilihan di form latihan', function () {
    Mapel::factory()->wajib()->create(['nama' => 'Kimia Wajib']);
    Mapel::factory()->pilihanUmum()->create(['nama' => 'Kimia Pilihan']);
    Mapel::factory()->pilihanUmum()->create(['nama' => 'Sejarah Luar Pilihan']);
    $peserta = User::factory()->peserta()->create();
    $peserta->mapelPilihan()->sync([Mapel::query()->where('nama', 'Kimia Pilihan')->sole()->getKey()]);

    $response = $this->actingAs($peserta)->get(route('latihan.index'))->assertOk();

    expect($response->viewData('mapels')->pluck('nama')->all())
        ->toBe(['Kimia Wajib', 'Kimia Pilihan']);
});

it('peserta tetap bisa berlatih pada mapel di luar pilihannya', function () {
    $this->seed();

    $terpilih = Mapel::query()->whereNull('deleted_at')->where('kode', 'FIS')->sole();
    $diLuarku = Mapel::query()->whereNull('deleted_at')->where('kode', 'MTK')->sole();
    $peserta = User::factory()->peserta()->create();
    $peserta->mapelPilihan()->sync([$terpilih->getKey()]);

    $this->actingAs($peserta)->post(route('latihan.mulai'), [
        'mapel_id' => $diLuarku->getKey(),
        'jumlah_soal' => 3,
        'timer' => 'stopwatch',
    ])->assertRedirect();

    expect(Percobaan::query()->where('user_id', $peserta->getKey())->count())->toBe(1);
});

it('pilihan mapel tidak membatasi daftar paket tryout', function () {
    $diLuarku = Mapel::factory()->create(['nama' => 'Bahasa Arab Luar Pilihan']);
    $paket = PaketTryout::factory()->create();
    $peserta = User::factory()->peserta()->create();
    $peserta->mapelPilihan()->sync([$diLuarku->getKey()]);

    $this->actingAs($peserta)->get(route('tryout.index'))
        ->assertOk()
        ->assertSee($paket->daftarMapel()->with('mapel')->get()->first()->mapel->nama);
});

it('halaman profil menampilkan mapel wajib sebagai keterangan, bukan opsi pilihan', function () {
    $wajib = Mapel::factory()->wajib()->create(['nama' => 'Matematika Wajib']);
    $pilihan = Mapel::factory()->pilihanUmum()->create(['nama' => 'Kimia Pilihan']);
    $peserta = User::factory()->peserta()->create();

    $response = $this->actingAs($peserta)->get(route('profil.show'))->assertOk();

    expect($response->viewData('wajib')->pluck('id')->all())->toBe([$wajib->getKey()])
        ->and($response->viewData('pilihan')->pluck('id')->all())->toBe([$pilihan->getKey()]);

    $html = $response->getContent();

    expect($html)->toContain('Matematika Wajib')
        ->toContain('Kimia Pilihan')
        ->and(preg_match('/name="mapel_pilihan\[\]" value="'.$wajib->getKey().'"/', $html))->toBe(0);
});

it('profil menolak lebih dari dua mapel pilihan', function () {
    Mapel::factory()->pilihanUmum()->count(3)->create();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->put(route('profil.update'), [
        'nama_lengkap' => 'Siti Aminah',
        'mapel_pilihan' => Mapel::query()->pluck('id')->all(),
    ])->assertSessionHasErrors('mapel_pilihan');
});

it('profil menolak mapel wajib sebagai pilihan', function () {
    $wajib = Mapel::factory()->wajib()->create();
    Mapel::factory()->pilihanUmum()->create();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->put(route('profil.update'), [
        'nama_lengkap' => 'Siti Aminah',
        'mapel_pilihan' => [$wajib->getKey()],
    ])->assertSessionHasErrors('mapel_pilihan.0');
});

it('mapel wajib tetap ditawarkan meski peserta memilih pilihan lain', function () {
    Mapel::factory()->wajib()->create(['nama' => 'Matematika']);
    $terpilih = Mapel::factory()->pilihanUmum()->create(['nama' => 'Kimia']);
    Mapel::factory()->pilihanUmum()->create(['nama' => 'Sejarah']);
    $peserta = User::factory()->peserta()->create();
    $peserta->mapelPilihan()->sync([$terpilih->getKey()]);

    $analisis = $this->actingAs($peserta)->get(route('analisis.index'))->assertOk();
    $latihan = $this->actingAs($peserta)->get(route('latihan.index'))->assertOk();

    foreach ([$analisis, $latihan] as $halaman) {
        expect($halaman->viewData('mapels')->pluck('nama')->all())
            ->toEqualCanonicalizing(['Matematika', 'Kimia']);
    }
});

it('halaman profil menyediakan form ganti kata sandi sendiri', function () {
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->get(route('profil.show'))
        ->assertOk()
        ->assertSee(route('profil.password'))
        ->assertSee('Kata sandi lama')
        ->assertSee('Kata sandi baru');
});

it('ganti kata sandi memutarkan sesi dan tetap masuk di perangkat ini', function () {
    $peserta = User::factory()->peserta()->create();
    // Nilai awal diisi sama seperti hasil login — factory tidak menyetelnya,
    // padahal tanpa token lama "perangkat lain ikut terbubarkan" tidak ada
    // artinya.
    $peserta->forceFill(['session_token' => 'token-perangkat-lama'])->save();
    $tokenLama = $peserta->session_token;

    // Sesi ini meniru perangkat yang sudah masuk: ia memegang token yang sama
    // dengan basis data, jadi `EnsureSingleSession` membiarkannya lewat —
    // kalau tidak, PUT-nya akan dibubarkan sebelum menyentuh controller.
    $this->withSession([EnsureSingleSession::SESSION_TOKEN_KEY => $tokenLama]);

    $this->actingAs($peserta)->put(route('profil.password'), [
        'password_lama' => 'password',
        'password' => 'kunci-baru-99',
        'password_confirmation' => 'kunci-baru-99',
    ])->assertRedirect(route('profil.show'));

    $peserta->refresh();

    // Dua hal yang harus terjadi bersamaan: seluruh sesi lain ikut terbubarkan
    // karena mereka masih memegang token lama, sementara perangkat yang sedang
    // dipakai justru diberi token yang baru.
    expect($peserta->session_token)->not->toBe($tokenLama)
        ->and(Hash::check('kunci-baru-99', $peserta->password))->toBeTrue();

    $this->get(route('profil.show'))->assertOk();

    $this->withSession([EnsureSingleSession::SESSION_TOKEN_KEY => $tokenLama])
        ->get(route('profil.show'))
        ->assertRedirect(route('login'));
});

it('ganti kata sandi menolak kata sandi lama yang salah', function () {
    $peserta = User::factory()->peserta()->create();
    $hashLama = $peserta->password;

    $this->from(route('profil.show'))
        ->actingAs($peserta)->put(route('profil.password'), [
            'password_lama' => 'bukan-ini',
            'password' => 'kunci-baru-99',
            'password_confirmation' => 'kunci-baru-99',
        ])->assertRedirect(route('profil.show'))
        ->assertSessionHasErrors('password_lama');

    expect($peserta->refresh()->password)->toBe($hashLama);
});

it('ganti kata sandi menolak sandi baru yang terlalu pendek atau tanpa konfirmasi', function () {
    $peserta = User::factory()->peserta()->create();
    // Token diisi lebih dulu supaya "tidak ikut berputar" bisa benar-benar
    // diperiksa — kalau nilainya null, kegagalan validasi memang tak akan
    // mengubah apa pun dan test ini jadi tidak membuktikan apa-apa.
    $peserta->forceFill(['session_token' => 'token-lama'])->save();
    // Sesi disemai seperti perangkat yang sudah masuk; tanpa ini
    // `EnsureSingleSession` membubarkan permintaan sebelum validasi berjalan.
    $this->withSession([EnsureSingleSession::SESSION_TOKEN_KEY => 'token-lama']);
    $hashLama = $peserta->password;
    $tokenLama = $peserta->session_token;

    $this->actingAs($peserta)->put(route('profil.password'), [
        'password_lama' => 'password',
        'password' => 'pendek',
        'password_confirmation' => 'pendek',
    ])->assertSessionHasErrors('password');

    $this->actingAs($peserta)->put(route('profil.password'), [
        'password_lama' => 'password',
        'password' => 'kunci-baru-99',
        'password_confirmation' => 'beda-lagi',
    ])->assertSessionHasErrors('password');

    expect($peserta->refresh()->password)->toBe($hashLama)
        ->and($peserta->session_token)->toBe($tokenLama);
});

it('profil tidak lagi menawarkan tingkat SD dan SMP', function () {
    $peserta = User::factory()->peserta()->create();

    $html = $this->actingAs($peserta)->get(route('profil.show'))->assertOk()->getContent();

    expect(ProfilController::TINGKAT_OPSI)->toBe(['SMA', 'SMK'])
        ->and($html)
        ->not->toMatch('/<option value="SD"/')
        ->not->toMatch('/<option value="SMP"/');
});
