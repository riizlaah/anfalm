<?php

use App\Models\User;

it('pendaftaran berhasil membuat akun ber-role peserta dan langsung login', function () {
    $response = $this->post('/register', [
        'nama_lengkap' => 'Ahmad Zaki',
        'email' => 'ahmad@anfalm.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'ahmad@anfalm.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(User::ROLE_PESERTA)
        ->and(auth()->check())->toBeTrue();
});

it('email harus unik', function () {
    User::factory()->create(['email' => 'duplikat@anfalm.test']);

    $this->post('/register', [
        'nama_lengkap' => 'Dewi',
        'email' => 'duplikat@anfalm.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertSessionHasErrors('email');
});

it('password wajib minimal 8 karakter', function () {
    $this->post('/register', [
        'nama_lengkap' => 'Budi',
        'email' => 'budi@anfalm.test',
        'password' => 'pendek',
        'password_confirmation' => 'pendek',
    ])->assertSessionHasErrors('password');
});

it('password wajib terkonfirmasi', function () {
    $this->post('/register', [
        'nama_lengkap' => 'Cici',
        'email' => 'cici@anfalm.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'bedalagi',
    ])->assertSessionHasErrors('password');
});
