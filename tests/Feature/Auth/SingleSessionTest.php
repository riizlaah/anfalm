<?php

use App\Http\Middleware\EnsureSingleSession;
use App\Models\User;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('setiap login baru mengganti token sesi akun (edge 6.5)', function () {
    $user = User::factory()->create();
    $credentials = ['email' => $user->email, 'password' => 'password'];

    $this->post('/login', $credentials)->assertRedirect('/dashboard');
    $tokenPerangkatA = $user->fresh()->session_token;
    expect($tokenPerangkatA)->not->toBeNull();

    $this->withoutMiddleware([RedirectIfAuthenticated::class]);

    $this->post('/login', $credentials)->assertRedirect('/dashboard');
    $tokenPerangkatB = $user->fresh()->session_token;

    expect($tokenPerangkatB)->not->toBe($tokenPerangkatA);
});

it('middleware mengeluarkan dan mengalihkan sesi dengan token tidak cocok', function () {
    $user = User::factory()->create(['session_token' => 'token-benar']);

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $store = $this->app['session.store'];
    $request->setLaravelSession($store);
    $store->put('tp_token', 'token-lama-usang');

    $response = (new EnsureSingleSession)->handle($request, fn () => new Response('next'));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toContain('/login')
        ->and($store->get('tp_token'))->toBeNull();
});

it('middleware membiarkan sesi dengan token yang cocok', function () {
    $user = User::factory()->create(['session_token' => 'token-benar']);

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $store = $this->app['session.store'];
    $request->setLaravelSession($store);
    $store->put('tp_token', 'token-benar');

    $response = (new EnsureSingleSession)->handle($request, fn () => new Response('next'));

    expect($response->getContent())->toBe('next');
});

it('middleware mengabaikan tamu', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => null);
    $request->setLaravelSession($this->app['session.store']);

    $response = (new EnsureSingleSession)->handle($request, fn () => new Response('next'));

    expect($response->getContent())->toBe('next');
});
