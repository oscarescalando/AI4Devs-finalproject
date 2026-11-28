<?php

declare(strict_types=1);

use App\Actions\Registration\BuildEntrySheet;
use App\Actions\Results\RecalculateEventResults;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\TimingMark;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * ISSUE-163 (TQ-034, ADR-017) — Planilla PDF por inscripción: descarga
 * autenticada con entries.view (binding por ULID público, 404 propia),
 * binario application/pdf y SIN PII (documento y nacimiento jamás; menores
 * con iniciales, INV-06). El contenido del PDF se valida en dos capas: el
 * binario es un smoke (%PDF, sin PII en crudo) y el TEXTO se comprueba en
 * la vista renderizada con los datos de la Action (dompdf comprime sus
 * streams: no hay extracción de texto fiable sin otra dependencia).
 */
uses(RefreshDatabase::class);

function sheetAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function sheetEvent(): Event
{
    $suffix = str()->random(6);

    return Event::query()->create([
        'name' => "Planilla $suffix",
        'slug' => "planilla-$suffix",
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'live',
    ]);
}

/** Documento y nacimiento DISTINTIVOS: la prueba de PII los busca literal. */
function sheetPerson(string $document, string $birthdate): Person
{
    $suffix = str_replace('-', '', $document);

    return Person::query()->create([
        'document' => $document,
        'first_name' => 'Nombre'.$suffix,
        'last_name' => 'Apellido'.$suffix,
        'birthdate' => $birthdate,
        'gender' => 'M',
    ]);
}

/**
 * Inscripción con su resultado provisional (salida real + llegada + motor).
 */
function sheetEntryWithResult(Event $event, Person $person, int $bib): Entry
{
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = Wave::query()->create([
        'race_id' => $race->id,
        'name' => 'General',
        'scheduled_start_at' => '2026-12-05 08:00:00.000',
        'actual_start_at' => '2026-12-05 08:00:00.000',
    ]);

    $entry = Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39'])->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => $bib,
        'status' => 'registered',
    ]);

    TimingMark::query()->create([
        'event_id' => $event->id,
        'entry_id' => $entry->id,
        'client_mark_id' => (string) Str::uuid(),
        'captured_at' => CarbonImmutable::parse('2026-12-05 08:30:05.250'),
        'device_time_ms' => CarbonImmutable::parse('2026-12-05 08:30:05.250')->getTimestampMs(),
        'source' => 'station',
    ]);

    (new RecalculateEventResults)($event->id); // en vivo: provisional

    return $entry->fresh(['result']);
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Descarga HTTP (binario)
|--------------------------------------------------------------------------
*/

it('descarga la planilla como application/pdf con el nombre por dorsal', function () {
    $event = sheetEvent();
    $person = sheetPerson('V-9999999', '1990-03-15');
    $entry = sheetEntryWithResult($event, $person, 701);

    $response = $this->actingAs(sheetAccount('organizer'))
        ->get(route('entries.planilla', $entry->ulid));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $binary = (string) $response->getContent();

    // Smoke binario: es un PDF de verdad y en crudo no viaja la PII.
    expect(str_starts_with($binary, '%PDF'))->toBeTrue()
        ->and($binary)->not->toContain('V-9999999')
        ->and($binary)->not->toContain('1990-03-15');
});

it('redirige al login a invitados y rechaza con 403 sin entries.view', function () {
    $event = sheetEvent();
    $entry = sheetEntryWithResult($event, sheetPerson('V-8888888', '1991-04-20'), 702);

    $this->get(route('entries.planilla', $entry->ulid))
        ->assertRedirect(route('login'));

    $this->actingAs(sheetAccount('timekeeper')) // solo marks.*
        ->get(route('entries.planilla', $entry->ulid))
        ->assertForbidden();
});

it('devuelve 404 para un ULID inválido o desconocido', function () {
    $organizer = sheetAccount('organizer');

    // El patrón de ruta (26 alfanuméricos) rechaza cualquier otra cosa.
    $this->actingAs($organizer)->get('/entries/123/planilla')->assertNotFound();

    $this->actingAs($organizer)
        ->get('/entries/'.strtolower((string) Str::ulid()).'/planilla')
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Contenido: la VISTA renderizada con los datos de la Action
|--------------------------------------------------------------------------
*/

it('la planilla pinta dorsal, nombre público y resultado SIN documento ni nacimiento', function () {
    $event = sheetEvent();
    $person = sheetPerson('V-7777777', '1992-06-10');
    $entry = sheetEntryWithResult($event, $person, 703);

    $built = app(BuildEntrySheet::class)($entry);
    expect($built['filename'])->toBe('planilla-703.pdf');

    $html = view('pdf.entry-sheet', ['sheet' => $built['sheet']])->render();

    expect($html)
        ->toContain('703')
        ->toContain($person->publicName())
        ->toContain('21K')
        ->toContain('General')
        ->toContain('M 30-39')
        ->toContain('0:30:05.2') // net FormatDuration con décimas (1.805.250 ms)
        ->toContain('1')        // posición general
        // INV-06: ni documento ni nacimiento jamás.
        ->not->toContain($person->document)
        ->not->toContain('1992-06-10');
});

it('la planilla de un menor sale con iniciales y sin PII', function () {
    $event = sheetEvent();
    $minor = sheetPerson('V-6666666', '2013-09-01');
    $entry = sheetEntryWithResult($event, $minor, 704);

    $built = app(BuildEntrySheet::class)($entry);
    $html = view('pdf.entry-sheet', ['sheet' => $built['sheet']])->render();

    expect($html)
        ->toContain('N. '.ucfirst($minor->last_name))
        ->not->toContain($minor->first_name)
        ->not->toContain($minor->document)
        ->not->toContain('2013-09-01');
});

it('la planilla sin llegada avisa que no hay resultado', function () {
    $event = sheetEvent();
    $person = sheetPerson('V-5555555', '1993-02-02');

    // Misma carrera/oleada, pero SIN marca: no hay resultado.
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    $wave = Wave::query()->create([
        'race_id' => $race->id,
        'name' => 'Única',
        'scheduled_start_at' => '2026-12-05 09:00:00.000',
        'actual_start_at' => null,
    ]);

    $entry = Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => Category::query()->create(['event_id' => $event->id, 'name' => 'M 20-29'])->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => 705,
        'status' => 'cancelled',
    ]);

    $built = app(BuildEntrySheet::class)($entry);
    $html = view('pdf.entry-sheet', ['sheet' => $built['sheet']])->render();

    expect($html)
        ->toContain('Sin llegada registrada.')
        ->toContain('Cancelada')
        ->not->toContain($person->document)
        ->not->toContain('1993-02-02');
});

/*
|--------------------------------------------------------------------------
| Listado: enlace de descarga por fila
|--------------------------------------------------------------------------
*/

it('el listado de inscripciones enlaza la planilla por fila', function () {
    $event = sheetEvent();
    $entry = sheetEntryWithResult($event, sheetPerson('V-4444444', '1994-07-07'), 706);

    $html = (string) $this->actingAs(sheetAccount('organizer'))
        ->get(route('events.entries', $event->ulid))
        ->getContent();

    expect($html)
        ->toContain('href="'.route('entries.planilla', $entry->ulid).'"')
        ->toContain('Descargar planilla PDF');
});
