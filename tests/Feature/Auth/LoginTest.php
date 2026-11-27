<?php

declare(strict_types=1);

use App\Models\Account;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;

/**
 * ISSUE-108 (RF-68) — Login por sesiones en Livewire: documento o correo,
 * remember, bloqueo 5/min, solo cuentas activas, intended y 419 a login.
 */
uses(RefreshDatabase::class);

function accountWithPassword(string $password = 'secreta-123', bool $active = true): Account
{
    $factory = $active ? Account::factory() : Account::factory()->inactive();

    return $factory->create(['password' => Hash::make($password)]);
}

it('muestra el login a invitados sin pistas del sistema', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Documento o correo')
        ->assertSee('Contraseña');
});

it('redirige a la consola si ya hay sesión', function () {
    $account = Account::factory()->create();

    $this->actingAs($account)
        ->get(route('login'))
        ->assertRedirect(route('panel.home'));
});

it('autentica por correo y regenera la sesión', function () {
    $account = accountWithPassword();

    Livewire::test('auth.login')
        ->set('login', $account->email)
        ->set('password', 'secreta-123')
        ->call('authenticate')
        ->assertRedirect(route('panel.home'));

    $this->assertAuthenticatedAs($account, 'web');
});

it('autentica por documento de la persona', function () {
    $account = accountWithPassword();

    Livewire::test('auth.login')
        ->set('login', $account->person->document)
        ->set('password', 'secreta-123')
        ->call('authenticate')
        ->assertRedirect(route('panel.home'));

    $this->assertAuthenticatedAs($account, 'web');
});

it('rechaza credenciales inválidas con mensaje genérico bajo el campo', function () {
    $account = accountWithPassword();

    Livewire::test('auth.login')
        ->set('login', $account->email)
        ->set('password', 'otra-cosa')
        ->call('authenticate')
        ->assertHasErrors(['login']);

    $this->assertGuest();
});

it('rechaza usuarios inexistentes con el mismo mensaje (sin enumeración)', function () {
    Livewire::test('auth.login')
        ->set('login', 'nadie@ejemplo.com')
        ->set('password', 'lo-que-sea')
        ->call('authenticate')
        ->assertHasErrors(['login']);

    $this->assertGuest();
});

it('rechaza cuentas inactivas aunque la contraseña sea correcta (RF-68)', function () {
    $account = accountWithPassword(active: false);

    Livewire::test('auth.login')
        ->set('login', $account->email)
        ->set('password', 'secreta-123')
        ->call('authenticate')
        ->assertHasErrors(['login']);

    $this->assertGuest();
});

it('bloquea tras 5 intentos fallidos por minuto (RF-68)', function () {
    $account = accountWithPassword();

    $component = Livewire::test('auth.login')->set('login', $account->email);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $component->set('password', 'mala-'.$attempt)->call('authenticate');
    }

    // El sexto intento con la contraseña CORRECTA sigue bloqueado.
    $component->set('password', 'secreta-123')->call('authenticate')
        ->assertHasErrors(['login']);

    $this->assertGuest();
});

it('respeta la ruta intentada tras autenticarse', function () {
    $account = accountWithPassword();

    $this->withSession(['url.intended' => '/destino-operativo']);

    Livewire::test('auth.login')
        ->set('login', $account->email)
        ->set('password', 'secreta-123')
        ->call('authenticate')
        ->assertRedirect('/destino-operativo');
});

it('cierra la sesión por POST e invalida la sesión', function () {
    $account = Account::factory()->create();

    $this->actingAs($account)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('la sesión expirada (419) lleva al login con mensaje y conserva el destino', function () {
    $request = Request::create('/destino-protegido', 'POST');
    $request->setLaravelSession($this->app['session.store']);
    // redirect()->guest() lee la URL del request activo del contenedor.
    $this->app->instance('request', $request);

    $response = $this->app->make(ExceptionHandler::class)
        ->render($request, new TokenMismatchException('csrf'));

    $intended = $this->app['session.store']->get('url.intended');

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('login'))
        // redirect()->guest() guarda el destino del request activo: basta
        // comprobar que la sesión quedó marcada con la ruta a reanudar.
        ->and($intended)->not->toBeNull()
        ->and($this->app['session.store']->get('status'))->toBe(__('auth.session_expired'));
});

it('la API no recibe la redirección de sesión expirada', function () {
    $request = Request::create('/api/v1/time', 'POST');
    $request->headers->set('Accept', 'application/json');
    $request->setLaravelSession($this->app['session.store']);

    $response = $this->app->make(ExceptionHandler::class)
        ->render($request, new TokenMismatchException('csrf'));

    expect($response)->not->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(419);
});
