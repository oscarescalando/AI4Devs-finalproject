<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Event;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

/**
 * ISSUE-141/143 (TQ-017) — Autorización de canales (RF-75, ADR-013 §3):
 * `event.{ulid}.public` admite anónimos solo si el evento existe (feed sin
 * PII) y `event.{ulid}.ops` exige sesión con permiso operativo del catálogo
 * (RolesPermissionsSeeder); `athlete` no tiene ninguno. El ámbito por
 * equipo-evento llega en F2.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function channelsDemoEvent(): Event
{
    return Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
}

function channelsAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

/**
 * Autenticador registrado en routes/channels.php para un patrón exacto: se
 * saca del broadcaster (el mismo objeto que Laravel consulta al pedir
 * /broadcasting/auth) para ejercitar la decisión de acceso tal cual quedó
 * registrada en el arranque de la app.
 *
 * @return Closure(Account|null, string): bool
 */
function channelsAuthenticator(string $pattern): Closure
{
    $broadcaster = Broadcast::driver();

    $property = new ReflectionProperty($broadcaster, 'channels');
    $channels = (array) $property->getValue($broadcaster);

    expect($channels)->toHaveKey($pattern);

    /** @var Closure(Account|null, string): bool $authenticator */
    $authenticator = $channels[$pattern];

    return $authenticator;
}

/**
 * Conmuta el broadcaster por defecto al driver reverb con credenciales
 * sintéticas y re-registra routes/channels.php (que se cargó sobre el
 * driver activo al arrancar, `null` en pruebas). La firma de
 * /broadcasting/auth es un HMAC local del SDK: no interviene ningún WS
 * real, así que la prueba no necesita Reverb levantado.
 */
function channelsUseReverbDriver(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'tdchronos-testing-key',
        'broadcasting.connections.reverb.secret' => 'tdchronos-testing-secret',
        'broadcasting.connections.reverb.app_id' => 'tdchronos-testing',
    ]);

    require base_path('routes/channels.php');
}

it('autoriza el canal público de un evento real, incluso anónimo, y rechaza ULID inexistentes', function () {
    $event = channelsDemoEvent();
    $public = channelsAuthenticator('event.{ulid}.public');

    expect($public(null, $event->ulid))->toBeTrue()
        ->and($public(channelsAccount('athlete'), $event->ulid))->toBeTrue()
        // Sin canales fantasma: un ULID que no es evento no autoriza.
        ->and($public(null, (string) Str::ulid()))->toBeFalse();
});

it('el canal ops exige sesión con algún permiso operativo del catálogo', function () {
    $event = channelsDemoEvent();
    $ops = channelsAuthenticator('event.{ulid}.ops');

    expect($ops(null, $event->ulid))->toBeFalse()
        ->and($ops(channelsAccount('athlete'), $event->ulid))->toBeFalse()
        ->and($ops(channelsAccount('organizer'), $event->ulid))->toBeTrue()
        ->and($ops(channelsAccount('official'), $event->ulid))->toBeTrue()
        ->and($ops(channelsAccount('timekeeper'), $event->ulid))->toBeTrue()
        // Aunque el rol opere: sin evento real no hay canal que autorizar.
        ->and($ops(channelsAccount('organizer'), (string) Str::ulid()))->toBeFalse();
});

it('el endpoint de suscripción rechaza al invitado en el canal privado ops', function () {
    channelsUseReverbDriver();
    $event = channelsDemoEvent();

    $this->postJson('/broadcasting/auth', [
        'channel_name' => "private-event.{$event->ulid}.ops",
        'socket_id' => '1000.1',
    ])->assertForbidden();
});

it('el endpoint firma la suscripción de ops al rol autorizado y rechaza al atleta', function () {
    channelsUseReverbDriver();
    $event = channelsDemoEvent();

    $this->actingAs(channelsAccount('organizer'))
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-event.{$event->ulid}.ops",
            'socket_id' => '1000.1',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs(channelsAccount('athlete'))
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-event.{$event->ulid}.ops",
            'socket_id' => '1000.1',
        ])
        ->assertForbidden();
});
