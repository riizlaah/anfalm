<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('tamu yang membuka /admin/user dialihkan ke login', function () {
    $this->get('/admin/user')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin/user (403)', function () {
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($peserta)->get('/admin/user')->assertForbidden();
});

it('admin dapat membuka halaman index pengguna', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create([
        'nama_lengkap' => 'Siswa Uji',
        'email' => 'siswa.uji@example.org',
    ]);

    $this->actingAs($admin)->get('/admin/user')
        ->assertOk()
        ->assertSee('Manajemen Pengguna')
        ->assertSee('Siswa Uji')
        ->assertSee('siswa.uji@example.org')
        ->assertSee(route('admin.user.edit', $peserta), false);
});

it('admin dapat mengubah email dan peran pengguna lain', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($admin)->put("/admin/user/{$peserta->getKey()}", [
        'nama_lengkap' => 'Nama Baru',
        'email' => 'barulagi@example.org',
        'role' => User::ROLE_ADMIN,
    ])
        ->assertRedirect(route('admin.user.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'id' => $peserta->getKey(),
        'nama_lengkap' => 'Nama Baru',
        'email' => 'barulagi@example.org',
        'role' => 'admin',
    ]);
});

it('validasi pengguna: nama, email dan role wajib, email harus unik', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create();
    User::factory()->peserta()->create(['email' => 'dipakai@example.org']);

    $this->actingAs($admin)->put("/admin/user/{$peserta->getKey()}", [])
        ->assertSessionHasErrors(['nama_lengkap', 'email', 'role']);

    $this->actingAs($admin)->put("/admin/user/{$peserta->getKey()}", [
        'nama_lengkap' => 'Masih Sah',
        'email' => 'dipakai@example.org',
        'role' => 'peserta',
    ])->assertSessionHasErrors(['email']);
});

it('email unik mengabaikan baris yang sedang diedit', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create(['email' => 'tetap@example.org']);

    $this->actingAs($admin)->put("/admin/user/{$peserta->getKey()}", [
        'nama_lengkap' => 'Masih Sah',
        'email' => 'tetap@example.org',
        'role' => 'peserta',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.user.index'));
});

it('admin dapat mereset kata sandi pengguna', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create(['password' => Hash::make('password')]);

    $this->actingAs($admin)->post("/admin/user/{$peserta->getKey()}/reset-password", [
        'password' => 'kataSandiBaru',
        'password_confirmation' => 'kataSandiBaru',
    ])
        ->assertRedirect(route('admin.user.index'))
        ->assertSessionHas('success');

    expect(Hash::check('kataSandiBaru', $peserta->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $peserta->fresh()->password))->toBeFalse();
});

it('reset kata sandi memutus sesi pengguna yang sedang berjalan', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create(['session_token' => Str::random(64)]);
    $sebelum = $peserta->fresh()->session_token;

    $this->actingAs($admin)->post("/admin/user/{$peserta->getKey()}/reset-password", [
        'password' => 'kataSandiBaru',
        'password_confirmation' => 'kataSandiBaru',
    ])->assertRedirect(route('admin.user.index'));

    expect($peserta->fresh()->session_token)->not->toBe($sebelum)
        ->and($peserta->fresh()->session_token)->not->toBeNull();
});

it('halaman kelola pengguna tidak menyediakan hapus', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->peserta()->create();

    expect(Route::has('admin.user.destroy'))->toBeFalse()
        ->and(Route::has('admin.user.store'))->toBeFalse();

    $html = $this->actingAs($admin)->get('/admin/user')->assertOk()->getContent();

    expect($html)->not->toContain('Hapus');
});

it('admin tidak dapat mengelola akunnya sendiri dari halaman ini', function () {
    $admin = User::factory()->admin()->create([
        'nama_lengkap' => 'Admin Lama',
        'email' => 'admin.lama@example.org',
    ]);
    $sandiAwal = $admin->password;

    $this->actingAs($admin)->get(route('admin.user.edit', $admin))
        ->assertRedirect(route('admin.user.index'))
        ->assertSessionHas('error');

    $this->actingAs($admin)->put(route('admin.user.update', $admin), [
        'nama_lengkap' => 'Admin Baru',
        'email' => 'admin.baru@example.org',
        'role' => 'peserta',
    ])
        ->assertRedirect(route('admin.user.index'))
        ->assertSessionHas('error');

    $this->actingAs($admin)->post(route('admin.user.reset-password', $admin), [
        'password' => 'kataSandiBaru',
        'password_confirmation' => 'kataSandiBaru',
    ])
        ->assertRedirect(route('admin.user.index'))
        ->assertSessionHas('error');

    // Satu aturan menutup dua bahaya: admin terakhir menurunkan perannya
    // sendiri (tidak ada yang bisa menaikkannya kembali), dan reset kata sandi
    // sendiri memutar `session_token` sehingga admin itu keluar di tengah
    // pekerjaannya.
    expect($admin->fresh()->nama_lengkap)->toBe('Admin Lama')
        ->and($admin->fresh()->email)->toBe('admin.lama@example.org')
        ->and($admin->fresh()->role)->toBe(User::ROLE_ADMIN)
        ->and($admin->fresh()->password)->toBe($sandiAwal);
});

it('reset kata sandi menolak kata sandi yang terlalu pendek', function () {
    $admin = User::factory()->admin()->create();
    $peserta = User::factory()->peserta()->create();

    $this->actingAs($admin)->post("/admin/user/{$peserta->getKey()}/reset-password", [
        'password' => 'pendek',
        'password_confirmation' => 'pendek',
    ])->assertSessionHasErrors(['password']);
});
