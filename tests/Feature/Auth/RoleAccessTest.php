<?php

use App\Models\User;

it('tamu yang membuka /admin dialihkan ke halaman login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('peserta tidak dapat membuka /admin (403)', function () {
    $user = User::factory()->peserta()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('admin dapat membuka /admin', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Dashboard Admin');
});

it('peserta melihat nama dan perannya di dashboard', function () {
    $user = User::factory()->peserta()->create(['nama_lengkap' => 'Veronika']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Veronika')
        ->assertSee('Peserta');
});

it('admin melihat dashboard umum sebagai admin', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});
