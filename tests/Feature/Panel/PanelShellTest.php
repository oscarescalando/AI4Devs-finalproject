<?php

declare(strict_types=1);

use App\Models\Account;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * ISSUE-107 — Shell del panel: acceso por sesión, menú desde permisos
 * reales y 403 server-side (la UI oculta; el servidor autoriza, ADR-011).
 */
uses(RefreshDatabase::class);

function accountWithRole(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);

    // Rutas de muestra con middleware de permisos: menú y 403 se prueban
    // contra rutas reales (las definitivas llegan con ISSUE-115).
    Route::get('_panel/events', fn () => 'ok')->name('events.index')->middleware('permission:events.view');
    Route::get('_panel/persons', fn () => 'ok')->name('directory.persons')->middleware('permission:directory.view');
    Route::get('_panel/entries', fn () => 'ok')->name('events.entries')->middleware('permission:entries.view');
    Route::get('_panel/start', fn () => 'ok')->name('events.start')->middleware('permission:start.manage');
    Route::get('_panel/marks', fn () => 'ok')->name('events.marks')->middleware('permission:marks.view');
    Route::get('_panel/results', fn () => 'ok')->name('events.results')->middleware('permission:results.view');
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
    $superadminHtml = $this->actingAs(accountWithRole('superadmin'))->get(route('panel.home'))->getContent();
    $timekeeperHtml = $this->actingAs(accountWithRole('timekeeper'))->get(route('panel.home'))->getContent();

    // Con las rutas de muestra registradas, el superadmin ve directorio y
    // operación completos; el cronometrador solo Registros (marks.view).
    expect($superadminHtml)->toContain('_panel/events')
        ->and($superadminHtml)->toContain('_panel/persons')
        ->and($superadminHtml)->toContain('_panel/marks')
        ->and($superadminHtml)->toContain('Directorio')
        ->and($timekeeperHtml)->toContain('_panel/marks')
        ->and($timekeeperHtml)->not->toContain('_panel/events')
        ->and($timekeeperHtml)->not->toContain('_panel/persons')
        ->and($timekeeperHtml)->not->toContain('_panel/entries')
        ->and($timekeeperHtml)->not->toContain('Directorio');
});

it('una ruta con permission: rechaza con 403 a quien no lo tiene', function () {
    $this->actingAs(accountWithRole('timekeeper'))->get('_panel/persons')->assertForbidden();
    $this->actingAs(accountWithRole('timekeeper'))->get('_panel/marks')->assertOk();
    $this->actingAs(accountWithRole('superadmin'))->get('_panel/persons')->assertOk();
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
