<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Support\FormatDate;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * ISSUE-115 (TQ-004) — Mapa de rutas to-be del panel: nombres registrados,
 * evento en el path resuelto por ULID, permiso por ruta (invitado, 403,
 * 200), 404 propia, evento activo en sesión para el menú y placeholders
 * navegables de lo que llega con EP-25.
 */
uses(RefreshDatabase::class);

/**
 * Nombres del mapa: la lista es código a propósito — un rename rompe esta
 * prueba antes de romper enlaces o el menú.
 */
function routeMapNames(): array
{
    return [
        'panel.home',
        'events.index',
        'events.show',
        'events.entries',
        'events.start',
        'events.marks',
        'events.results',
        'events.categories',
        'events.waves',
        'directory.organizations',
        'directory.persons',
        'directory.teams',
        'championships.index',
    ];
}

function routeMapAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

/** Evento sintético sin categorías ni oleadas: el caso del aviso del hub. */
function routeMapEvent(): Event
{
    return Event::query()->create([
        'name' => 'Ruta del Mapa 10K',
        'slug' => 'ruta-del-mapa-10k',
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'registration',
    ]);
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

it('cada nombre del mapa de rutas existe', function (string $name) {
    expect(Route::has($name))->toBeTrue("La ruta {$name} no está registrada (ISSUE-115).");
})->with(routeMapNames());

it('redirige al login a los invitados en todas las rutas del mapa', function (string $name) {
    $event = routeMapEvent();

    $byEvent = in_array($name, ['events.show', 'events.entries', 'events.start', 'events.marks', 'events.results', 'events.categories', 'events.waves'], true);
    $url = $byEvent ? route($name, $event) : route($name);

    $this->get($url)->assertRedirect(route('login'));
})->with(array_values(array_diff(routeMapNames(), ['panel.home'])));

it('exige events.view para el listado y el hub del evento', function () {
    $event = routeMapEvent();

    $this->actingAs(routeMapAccount('timekeeper'))->get(route('events.index'))->assertForbidden();
    $this->actingAs(routeMapAccount('timekeeper'))->get(route('events.show', $event))->assertForbidden();

    $this->actingAs(routeMapAccount('organizer'))->get(route('events.index'))->assertOk();
    $this->actingAs(routeMapAccount('organizer'))->get(route('events.show', $event))->assertOk();
});

it('devuelve la 404 propia para un evento inexistente o un id entero', function () {
    $organizer = routeMapAccount('organizer');

    $this->actingAs($organizer)->get('/events/'.strtolower((string) Str::ulid()))
        ->assertNotFound()
        ->assertSee('La página que buscas no existe.');

    // El id entero jamás es una clave pública (ADR-015): el patrón de ruta
    // lo rechaza con 404 sin tocar la base.
    $this->actingAs($organizer)->get('/events/123')->assertNotFound();
});

it('el hub fija el evento activo en sesión y el menú enlaza las operativas', function () {
    $event = routeMapEvent();
    $organizer = routeMapAccount('organizer');

    $this->actingAs($organizer)->get(route('events.show', $event))->assertOk();
    expect(session('tc.event_ulid'))->toBe($event->ulid);

    $html = $this->actingAs($organizer)->get(route('panel.home'))->getContent();

    expect($html)->toContain("/events/{$event->ulid}/entries")
        ->and($html)->toContain("/events/{$event->ulid}/marks")
        ->and($html)->toContain("/events/{$event->ulid}/results");
});

it('el menú renderiza sin evento activo apuntando al listado de eventos', function () {
    $html = $this->actingAs(routeMapAccount('organizer'))->get(route('panel.home'))->getContent();

    // Sin tc.event_ulid el grupo Operación cae al listado (URL absoluta,
    // anclada con la comilla final) y el menú renderiza sin excepciones.
    expect($html)->toContain('href="'.route('events.index').'"');
});

it('el hub avisa de forma accionable cuando falta configuración del evento', function () {
    $event = routeMapEvent();
    $organizer = routeMapAccount('organizer');

    $html = $this->actingAs($organizer)->get(route('events.show', $event))->getContent();

    expect($html)->toContain('Falta configuración del evento')
        ->and($html)->toContain('no tiene categorías configuradas')
        ->and($html)->toContain('no tiene oleadas configuradas')
        ->and($html)->toContain("/events/{$event->ulid}/categories")
        ->and($html)->toContain("/events/{$event->ulid}/waves");

    // El evento demo viene configurado: el aviso no aparece.
    $this->seed(DatabaseSeeder::class);
    $demo = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();

    $configured = $this->actingAs($organizer)->get(route('events.show', $demo))->getContent();

    expect($configured)->not->toContain('Falta configuración del evento');
});

it('inscripciones lista dorsales y atletas con el contrato público del roster', function () {
    $this->seed(DatabaseSeeder::class);
    $event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
    $first = Entry::query()->where('event_id', $event->id)->orderBy('bib')->firstOrFail();

    $response = $this->actingAs(routeMapAccount('organizer'))->get(route('events.entries', $event));

    $response->assertOk()
        ->assertSee('Inscripciones')
        ->assertSee($event->name)
        ->assertSee((string) $first->bib)
        ->assertSee($first->person->publicName());

    $html = $response->getContent();

    // Dorsal en mono tabular (§1.3) y paginación server-side de 10 en 10.
    expect($html)->toContain('td-right mono')
        ->and($html)->toContain('1–10 de 114')
        // Sin PII extra (INV-06): ni documento ni contacto en la pantalla.
        ->and($html)->not->toContain((string) $first->person->document);
});

it('las operativas y configuraciones sin pantalla avisan que llegan con EP-25', function (string $name) {
    $event = routeMapEvent();
    $organizer = routeMapAccount('organizer');

    $this->actingAs($organizer)->get(route($name, $event))
        ->assertOk()
        ->assertSee('Llega con su pantalla completa en EP-25');
})->with(['events.start', 'events.marks', 'events.results', 'events.categories', 'events.waves']);

it('las pantallas de directorio y liga avisan su llegada', function () {
    $superadmin = routeMapAccount('superadmin');

    $this->actingAs($superadmin)->get(route('directory.persons'))
        ->assertOk()->assertSee('EP-25');
    $this->actingAs($superadmin)->get(route('directory.teams'))
        ->assertOk()->assertSee('EP-25');
    $this->actingAs($superadmin)->get(route('championships.index'))
        ->assertOk()->assertSee('Liga — v2');
});

it('el listado de eventos muestra la tabla con fecha, estado y acceso al hub', function () {
    $event = routeMapEvent();

    $this->actingAs(routeMapAccount('organizer'))->get(route('events.index'))
        ->assertOk()
        ->assertSee($event->name)
        ->assertSee(FormatDate::date($event->date))
        ->assertSee('Inscripción'); // estado registration, etiqueta española
});

it('web.php no registra rutas resource sin implementación', function () {
    expect(file_get_contents(base_path('routes/web.php')))
        ->not->toMatch('/Route::(?:api)?resources?\s*\(/i');
});
