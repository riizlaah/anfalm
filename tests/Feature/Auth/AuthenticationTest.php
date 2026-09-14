<?php

use App\Models\User;

it('menampilkan halaman login', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk');
});

it('menampilkan halaman register', function () {
    $this->get('/register')->assertOk()->assertSee('Daftar');
});

it('login gagal mengembalikan ke halaman login dengan error', function () {
    User::factory()->create(['email' => 'coba@anfalm.test', 'password' => 'password']);

    $this->post('/login', [
        'email' => 'coba@anfalm.test',
        'password' => 'salah-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('login berhasil mengarahkan ke dashboard dan mengaktifkan sesi', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    expect(session('tp_token'))->not->toBeNull();
});

it('logout mengeluarkan pengguna dan mengarahkan ke login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('tamu tidak dapat membuka dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
