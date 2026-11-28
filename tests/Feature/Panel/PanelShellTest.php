<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Event;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ISSUE-107 — Shell del panel: acceso por sesión, menú desde permisos
 * reales y 403 server-side (la UI oculta; el servidor autoriza, ADR-011).
 * Desde ISSUE-115/TQ-004 el menú y el 403 se prueban contra las rutas
 * reales del mapa to-be (las de muestra se retiraron).
 */
uses(RefreshDatabase::class);

function accountWithRole(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function shellEvent(): Event
{
    return Event::query()->create([
        'name' => 'Panel Shell 10K',
        'slug' => 'panel-shell-10k',
        'date' => today()->format('Y-m-d'),
        'timezone' => 'America/Caracas',
        'status' => 'live',
    ]);
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

it('redirige a los invitados al login', function () {
    $this->get(route('panel.home'))->assertRedirect(route('login'));
});

it('la consola renderiza para cuentas autenticadas', function () {
    $this->actingAs(accountWithRole('timekeeper'))
        ->get(route('panel.home'))
        ->assertOk()
        ->assertSee('Consola');
});

it('el menú solo pinta las entradas con permiso y ruta existente', function () {
    $event = shellEvent();
    session(['tc.event_ulid' => $event->ulid]);

    $superadminHtml = $this->actingAs(accountWithRole('superadmin'))->get(route('panel.home'))->getContent();
    $timekeeperHtml = $this->actingAs(accountWithRole('timekeeper'))->get(route('panel.home'))->getContent();

    // Con el mapa to-be registrado, el superadmin ve directorio completo y
    // las operativas del evento activo; el cronometrador solo Registros.
    // (Las URLs del menú son absolutas: se ancla la comilla final para no
    // confundir /events con /events/{ulid}/…)
    $eventsUrl = route('events.index').'"';

    expect($superadminHtml)->toContain('href="'.$eventsUrl)
        ->and($superadminHtml)->toContain('/directory/persons')
        ->and($superadminHtml)->toContain('/directory/organizations')
        ->and($superadminHtml)->toContain("/events/{$event->ulid}/entries")
        ->and($superadminHtml)->toContain("/events/{$event->ulid}/marks")
        ->and($superadminHtml)->toContain('Directorio')
        ->and($timekeeperHtml)->toContain("/events/{$event->ulid}/marks")
        ->and($timekeeperHtml)->not->toContain('href="'.$eventsUrl)
        ->and($timekeeperHtml)->not->toContain('/directory/persons')
        ->and($timekeeperHtml)->not->toContain('/directory/organizations')
        ->and($timekeeperHtml)->not->toContain('/championships')
        ->and($timekeeperHtml)->not->toContain('Directorio');
});

it('una ruta con permission: rechaza con 403 a quien no lo tiene', function () {
    $event = shellEvent();

    $this->actingAs(accountWithRole('timekeeper'))->get(route('events.index'))->assertForbidden();
    $this->actingAs(accountWithRole('timekeeper'))->get(route('events.show', $event))->assertForbidden();
    $this->actingAs(accountWithRole('timekeeper'))->get(route('events.marks', $event))->assertOk();
    $this->actingAs(accountWithRole('superadmin'))->get(route('directory.persons'))->assertOk();
});

it('la matriz de roles respeta el SRS §2 (base global, equipo plataforma)', function () {
    $superadmin = accountWithRole('superadmin');
    $admin = accountWithRole('admin');
    $organizer = accountWithRole('organizer');
    $official = accountWithRole('official');
    $timekeeper = accountWithRole('timekeeper');

    expect($superadmin->can('roles.manage'))->toBeTrue()
        ->and($admin->can('directory.view'))->toBeTrue()
        ->and($admin->can('roles.manage'))->toBeFalse()
        ->and($organizer->can('events.manage'))->toBeTrue()
        ->and($organizer->can('accounts.manage'))->toBeFalse()
        ->and($official->can('corrections.manage'))->toBeTrue()
        ->and($timekeeper->can('marks.manage'))->toBeTrue()
        ->and($timekeeper->can('results.publish'))->toBeFalse();
});

it('el login no expone información del sistema', function () {
    $html = $this->get(route('login'))->getContent();

    expect($html)->not->toContain('superadmin')
        ->and($html)->not->toContain('cronometrador')
        ->and($html)->not->toContain('@tdchronos.test');
});
