<?php

use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\User;

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
        ->assertSee($paket->wajib1->nama);
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
