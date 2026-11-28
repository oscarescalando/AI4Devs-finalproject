<?php

declare(strict_types=1);

use App\Livewire\Events\EntryManager;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Wave;
use App\Support\MaskDocument;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ISSUE-162 (TQ-030) — Gestor de inscripciones: render por rol (entries.view
 * lee, entries.manage escribe), alta individual con categoría AUTOMÁTICA por
 * edad al día del evento + género (con previsualización y error accionable
 * sin match), dorsal sugerido consecutivo editable y único (INV-03), persona
 * única por evento aunque su inscripción esté cancelada (INV-04), baja lógica
 * con reactivación sin borrar la fila, y filtros/búsqueda del listado.
 */
uses(RefreshDatabase::class);

function entriesAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function makeEntriesEvent(string $name = 'Gestor 21K'): Event
{
    return Event::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower((string) Str::ulid()),
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'registration',
    ]);
}

/** Categorías como las del demo: M/F por década + Junior sin género. */
function seedEntryCategories(Event $event): void
{
    foreach ([
        ['M 20-29', 20, 29, false],
        ['M 30-39', 30, 39, false],
        ['F 20-29', 20, 29, false],
        ['F 30-39', 30, 39, false],
        ['Junior', 10, 19, false],
        ['Relevo Mixto', 18, null, true],
    ] as [$name, $min, $max, $forTeams]) {
        Category::query()->create([
            'event_id' => $event->id,
            'name' => $name,
            'for_teams' => $forTeams,
            'min_age' => $min,
            'max_age' => $max,
        ]);
    }
}

/**
 * @return array{0: Race, 1: Wave}
 */
function seedEntryRaceWave(Event $event, string $raceName = '21K', string $waveName = 'Única'): array
{
    $race = Race::query()->create(['event_id' => $event->id, 'name' => $raceName]);
    $wave = Wave::query()->create([
        'race_id' => $race->id,
        'name' => $waveName,
        'scheduled_start_at' => null,
        'actual_start_at' => null,
    ]);

    return [$race, $wave];
}

/** Persona cuya edad al día del evento es exactamente la pedida. */
function personOfAgeAt(Event $event, int $age, string $gender, string $document): Person
{
    // El nombre incluye el sufijo del documento (sin guion) para que sea
    // único en la tabla, pero JAMÁS el documento completo: la prueba de PII
    // exige que «V-…» no aparezca en la pantalla.
    $suffix = str_replace('-', '', $document);

    return Person::query()->create([
        'document' => $document,
        'first_name' => 'Nombre'.$suffix,
        'last_name' => 'Apellido'.$suffix,
        'birthdate' => Carbon::parse($event->date->format('Y-m-d'))->subYears($age)->subDay()->format('Y-m-d'),
        'gender' => $gender,
    ]);
}

function seedEntry(Event $event, Person $person, int $bib, string $status = 'registered'): Entry
{
    [$race, $wave] = seedEntryRaceWave($event);

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => Category::query()->where('event_id', $event->id)->firstOrFail()->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => $bib,
        'status' => $status,
    ]);
}

/**
 * Formulario de alta listo para guardar sobre la persona/carrera/oleada dadas.
 *
 * @param  array{person?: Person, race?: Race, wave?: Wave, bib?: int|null}  $overrides
 */
function fillEntryForm(object $component, Event $event, array $overrides = []): object
{
    [$race, $wave] = seedEntryRaceWave($event, '21K', 'General');
    $person = $overrides['person'] ?? personOfAgeAt($event, 31, 'M', 'V-1111111');

    return $component
        ->set('form.person_ulid', $person->ulid)
        ->set('form.race_ulid', ($overrides['race'] ?? $race)->ulid)
        ->set('form.wave_ulid', ($overrides['wave'] ?? $wave)->ulid)
        ->set('form.bib', (string) ($overrides['bib'] ?? 500));
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Acceso (la UI oculta; el servidor autoriza — ADR-011)
|--------------------------------------------------------------------------
*/

it('muestra el gestor de inscripciones a quien tiene entries.view', function (string $role) {
    $event = makeEntriesEvent();

    $this->actingAs(entriesAccount($role))
        ->get(route('events.entries', $event->ulid))
        ->assertOk()
        ->assertSee('Inscripciones')
        ->assertSee($event->name)
        ->assertSee('Nueva inscripción');
})->with(['organizer', 'official']);

it('rechaza con 403 a roles sin entries.view', function () {
    $event = makeEntriesEvent();

    $this->actingAs(entriesAccount('timekeeper'))
        ->get(route('events.entries', $event->ulid))
        ->assertForbidden();
});

it('devuelve 404 para un evento inexistente', function () {
    $this->actingAs(entriesAccount('organizer'))
        ->get('/events/'.strtolower((string) Str::ulid()).'/entries')
        ->assertNotFound();
});

it('el servidor rechaza escribir sin entries.manage aunque la UI se salte', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $entry = seedEntry($event, Person::factory()->create(), 101);
    $this->actingAs(entriesAccount('official')); // entries.view sin manage

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', '01J00000000000000000000000')
        ->call('save')
        ->assertForbidden();

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('cancelEntry', $entry->ulid)
        ->assertForbidden();

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('reactivateEntry', $entry->ulid)
        ->assertForbidden();

    expect(Entry::query()->count())->toBe(1)
        ->and($entry->fresh()->status)->toBe('registered');
});

/*
|--------------------------------------------------------------------------
| Listado (columnas, contrato público y paginación)
|--------------------------------------------------------------------------
*/

it('lista inscritos con dorsal mono, atleta, categoría, carrera y estado', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    [$race, $wave] = seedEntryRaceWave($event);
    $adult = personOfAgeAt($event, 31, 'M', 'V-2222222');
    $minor = personOfAgeAt($event, 12, 'F', 'V-3333333');
    $category = Category::query()->where('event_id', $event->id)->where('name', 'M 30-39')->firstOrFail();
    $junior = Category::query()->where('event_id', $event->id)->where('name', 'Junior')->firstOrFail();

    foreach ([[$adult, $category, 101], [$minor, $junior, 102]] as [$person, $cat, $bib]) {
        Entry::query()->create([
            'event_id' => $event->id,
            'category_id' => $cat->id,
            'race_id' => $race->id,
            'wave_id' => $wave->id,
            'person_id' => $person->id,
            'team_id' => null,
            'bib' => $bib,
        ]);
    }

    $html = (string) $this->actingAs(entriesAccount('organizer'))
        ->get(route('events.entries', $event->ulid))
        ->getContent();

    expect($html)
        ->toContain('<td class="td-right mono">101</td>')
        ->toContain($adult->first_name.' '.$adult->last_name)
        ->toContain('M 30-39')
        ->toContain('21K')
        ->toContain('Inscrito')
        // Menor con iniciales (INV-06) y sin documento en la pantalla.
        ->toContain('N. '.ucfirst($minor->last_name))
        ->not->toContain($minor->document)
        // La baja lógica es la acción visible de la fila activa.
        ->toContain('wire:confirm');
});

it('pagina de 25 en 25 con el total del evento', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    [$race, $wave] = seedEntryRaceWave($event);
    $category = Category::query()->where('event_id', $event->id)->firstOrFail();

    foreach (range(1, 27) as $bib) {
        Entry::query()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'race_id' => $race->id,
            'wave_id' => $wave->id,
            'person_id' => Person::factory()->create()->id,
            'team_id' => null,
            'bib' => $bib,
        ]);
    }

    $html = (string) $this->actingAs(entriesAccount('organizer'))
        ->get(route('events.entries', $event->ulid))
        ->getContent();

    expect($html)->toContain('1–25 de 27');
});

/*
|--------------------------------------------------------------------------
| Filtros y búsqueda (ISSUE-128)
|--------------------------------------------------------------------------
*/

it('filtra por carrera, categoría y estado y se limpia', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    [$race21k, $wave21k] = seedEntryRaceWave($event, '21K');
    [$race10k, $wave10k] = seedEntryRaceWave($event, '10K');
    $m30 = Category::query()->where('event_id', $event->id)->where('name', 'M 30-39')->firstOrFail();
    $f20 = Category::query()->where('event_id', $event->id)->where('name', 'F 20-29')->firstOrFail();

    $in21k = personOfAgeAt($event, 31, 'M', 'V-4444444');
    $in10k = personOfAgeAt($event, 25, 'F', 'V-5555555');
    $cancelled = personOfAgeAt($event, 35, 'M', 'V-6666666');

    foreach ([
        [$in21k, $m30, $race21k, $wave21k, 101, 'registered'],
        [$in10k, $f20, $race10k, $wave10k, 102, 'registered'],
        [$cancelled, $m30, $race21k, $wave21k, 103, 'cancelled'],
    ] as [$person, $cat, $race, $wave, $bib, $status]) {
        Entry::query()->create([
            'event_id' => $event->id,
            'category_id' => $cat->id,
            'race_id' => $race->id,
            'wave_id' => $wave->id,
            'person_id' => $person->id,
            'team_id' => null,
            'bib' => $bib,
            'status' => $status,
        ]);
    }

    // Los filtros COMBINAN: cada fase arranca soltando el anterior.
    $component = Livewire::withQueryParams(['race' => $race10k->ulid])
        ->test(EntryManager::class, ['event' => $event->ulid]);

    $component->assertSee($in10k->first_name)
        ->assertDontSee($in21k->first_name);

    $component->set('race', '')->set('category', $m30->ulid)
        ->assertSee($in21k->first_name)
        ->assertDontSee($in10k->first_name);

    $component->set('category', '')->set('status', 'cancelled')
        ->assertSee($cancelled->first_name)
        ->assertDontSee($in21k->first_name)
        ->assertDontSee($in10k->first_name);

    $component->call('clearFilters')
        ->assertSet('race', '')
        ->assertSet('category', '')
        ->assertSet('status', '')
        ->assertSee($in21k->first_name)
        ->assertSee($in10k->first_name);
});

it('busca por dorsal exacto y por nombre parcial', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $needle = personOfAgeAt($event, 31, 'M', 'V-7777777');
    $other = personOfAgeAt($event, 25, 'F', 'V-8888888');
    seedEntry($event, $needle, 205);
    seedEntry($event, $other, 206);

    $component = Livewire::test(EntryManager::class, ['event' => $event->ulid]);

    // Dorsal exacto: 205 trae solo al del 205.
    $component->set('q', '205')
        ->assertSee($needle->first_name)
        ->assertDontSee($other->first_name);

    // Nombre parcial: apellido único del otro atleta.
    $component->set('q', $other->last_name)
        ->assertSee($other->first_name)
        ->assertDontSee($needle->first_name);

    $component->set('q', '999')
        ->assertSee('Sin inscripciones');
});

/*
|--------------------------------------------------------------------------
| Alta individual: categoría automática y previsualización
|--------------------------------------------------------------------------
*/

it('previsualiza en vivo la categoría al elegir a la persona del picker', function (string $gender, int $age, string $expected) {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $person = personOfAgeAt($event, $age, $gender, 'V-999999'.$age);

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('personQuery', 'Apellido')
        ->assertSee($person->last_name) // adultos a nombre completo, menores a iniciales: el apellido siempre sale
        ->call('selectPerson', $person->ulid)
        ->assertSet('form.person_ulid', $person->ulid)
        ->assertSet('selected.category', $expected)
        ->assertSet('selected.age', $age)
        // El picker enmascara el documento (RNF-13).
        ->assertSet('selected.document', MaskDocument::mask($person->document));
})->with([
    '31 M' => ['M', 31, 'M 30-39'],
    '25 F' => ['F', 25, 'F 20-29'],
    '12 F' => ['F', 12, 'Junior'],
    '12 M' => ['M', 12, 'Junior'],
]);

it('previsualiza el fallo accionable cuando ninguna categoría cubre a la persona', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $person = personOfAgeAt($event, 70, 'M', 'V-1212121');

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('selectPerson', $person->ulid)
        ->assertSet('selected.category', null)
        ->assertSee('Sin categoría para edad 70 género M');
});

it('crea la inscripción con la categoría automática por edad y género', function (string $gender, int $age, string $expected) {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    seedEntryRaceWave($event);
    $this->actingAs(entriesAccount('organizer'));
    $person = personOfAgeAt($event, $age, $gender, 'V-131313'.$age);

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', $person->ulid)
        ->set('form.race_ulid', Race::query()->where('event_id', $event->id)->sole()->ulid)
        ->set('form.wave_ulid', Wave::query()->sole()->ulid)
        ->set('form.bib', '500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-entry-modal');

    $entry = Entry::query()->sole();

    expect($entry->category->name)->toBe($expected)
        ->and($entry->person_id)->toBe($person->id)
        ->and($entry->team_id)->toBeNull() // CHECK persona XOR equipo
        ->and($entry->bib)->toBe(500)
        ->and($entry->status)->toBe('registered');
})->with([
    '31 M' => ['M', 31, 'M 30-39'],
    '25 F' => ['F', 25, 'F 20-29'],
    '12 F' => ['F', 12, 'Junior'],
]);

it('sin categoría que cubra, la alta falla con error accionable y no crea nada', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    seedEntryRaceWave($event);
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', personOfAgeAt($event, 70, 'M', 'V-1414141')->ulid)
        ->set('form.race_ulid', Race::query()->where('event_id', $event->id)->sole()->ulid)
        ->set('form.wave_ulid', Wave::query()->sole()->ulid)
        ->set('form.bib', '500')
        ->call('save')
        ->assertHasErrors(['form.person_ulid'])
        ->assertSee('Sin categoría para edad 70 género M');

    expect(Entry::query()->count())->toBe(0);
});

it('exige persona, carrera y oleada con error bajo el campo', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('save')
        ->assertHasErrors(['form.person_ulid', 'form.race_ulid', 'form.wave_ulid'])
        ->assertNotDispatched('close-entry-modal');
});

it('rechaza la oleada de otra carrera y la carrera de otro evento', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $other = makeEntriesEvent('Evento Ajeno');
    seedEntryRaceWave($other);
    $this->actingAs(entriesAccount('organizer'));

    [$race21k, $wave21k] = seedEntryRaceWave($event, '21K');
    [, $wave10k] = seedEntryRaceWave($event, '10K');

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', personOfAgeAt($event, 31, 'M', 'V-1515151')->ulid)
        ->set('form.race_ulid', $race21k->ulid)
        ->set('form.wave_ulid', $wave10k->ulid)
        ->set('form.bib', '500')
        ->call('save')
        ->assertHasErrors(['form.wave_ulid']);

    $foreignRace = Race::query()->where('event_id', $other->id)->sole();

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', personOfAgeAt($event, 31, 'M', 'V-1616161')->ulid)
        ->set('form.race_ulid', $foreignRace->ulid)
        ->set('form.wave_ulid', Wave::query()->where('race_id', $foreignRace->id)->sole()->ulid)
        ->set('form.bib', '501')
        ->call('save')
        ->assertHasErrors(['form.race_ulid']);

    expect(Entry::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Dorsal (INV-03): sugerido consecutivo, editable y único
|--------------------------------------------------------------------------
*/

it('sugiere el dorsal siguiente al máximo del evento y lo usa al guardar', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    seedEntry($event, Person::factory()->create(), 204);
    $this->actingAs(entriesAccount('organizer'));

    $component = Livewire::test(EntryManager::class, ['event' => $event->ulid]);
    $component->call('startCreate')
        ->assertSet('form.bib', '205');

    fillEntryForm($component, $event, ['bib' => null])
        ->set('form.bib', '')
        ->call('save')
        ->assertHasNoErrors();

    $entry = Entry::query()->where('bib', 205)->firstOrFail();

    expect($entry->race->name)->toBe('21K')
        ->and($entry->category->name)->toBe('M 30-39');
});

it('arranca los dorsales en 1 cuando el evento está vacío', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('startCreate')
        ->assertSet('form.bib', '1');
});

it('rechaza un dorsal ocupado con error bajo el campo y no crea nada', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $taken = seedEntry($event, Person::factory()->create(), 204);
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', personOfAgeAt($event, 31, 'M', 'V-1717171')->ulid)
        ->set('form.race_ulid', $taken->race->ulid)
        ->set('form.wave_ulid', $taken->wave->ulid)
        ->set('form.bib', '204')
        ->call('save')
        ->assertHasErrors(['form.bib'])
        ->assertSee('El dorsal 204 ya está usado en este evento.');

    expect(Entry::query()->count())->toBe(1);
});

it('rechaza dorsales fuera de rango', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.bib', '100000')
        ->call('save')
        ->assertHasErrors(['form.bib']);
});

/*
|--------------------------------------------------------------------------
| Persona única por evento (INV-04)
|--------------------------------------------------------------------------
*/

it('rechaza inscribir dos veces a la misma persona con error claro', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $person = personOfAgeAt($event, 31, 'M', 'V-1818181');
    seedEntry($event, $person, 101);
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', $person->ulid)
        ->set('form.race_ulid', Race::query()->where('event_id', $event->id)->sole()->ulid)
        ->set('form.wave_ulid', Wave::query()->sole()->ulid)
        ->set('form.bib', '500')
        ->call('save')
        ->assertHasErrors(['form.person_ulid'])
        ->assertSee('ya tiene una inscripción en este evento');

    expect(Entry::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('una persona cancelada se reactiva, no se re-inscribe', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $person = personOfAgeAt($event, 31, 'M', 'V-1919191');
    $entry = seedEntry($event, $person, 101, 'cancelled');
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->set('form.person_ulid', $person->ulid)
        ->set('form.race_ulid', $entry->race->ulid)
        ->set('form.wave_ulid', $entry->wave->ulid)
        ->set('form.bib', '500')
        ->call('save')
        ->assertHasErrors(['form.person_ulid']);

    expect(Entry::query()->where('event_id', $event->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Baja lógica y reactivación (sin borrado físico)
|--------------------------------------------------------------------------
*/

it('cancela y reactiva ciclando el estado sin borrar la fila', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $person = personOfAgeAt($event, 31, 'M', 'V-2020202');
    $entry = seedEntry($event, $person, 101);
    $this->actingAs(entriesAccount('organizer'));

    $component = Livewire::test(EntryManager::class, ['event' => $event->ulid]);

    $component->call('cancelEntry', $entry->ulid)
        ->assertDispatched('toast', type: 'success');

    $entry->refresh();

    expect($entry->status)->toBe('cancelled')
        ->and(Entry::query()->count())->toBe(1);

    // La fila cancelada sigue en el listado, con su acción de retorno.
    $component->assertSee('Cancelada');

    $component->call('reactivateEntry', $entry->ulid)
        ->assertDispatched('toast', type: 'success');

    $entry->refresh();

    expect($entry->status)->toBe('registered')
        ->and(Entry::query()->count())->toBe(1)
        ->and($entry->bib)->toBe(101); // dorsal intacto tras el ciclo
});

it('no cancela dos veces ni reactiva una inscripción activa', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);
    $entry = seedEntry($event, Person::factory()->create(), 101);
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('reactivateEntry', $entry->ulid)
        ->assertDispatched('toast', type: 'error');

    $entry->refresh();
    $entry->update(['status' => 'cancelled']);

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('cancelEntry', $entry->ulid)
        ->assertDispatched('toast', type: 'error');

    expect($entry->fresh()->status)->toBe('cancelled')
        ->and(Entry::query()->count())->toBe(1);
});

it('avisa por toast cuando la inscripción ya no existe', function () {
    $event = makeEntriesEvent();
    $this->actingAs(entriesAccount('organizer'));

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->call('cancelEntry', strtolower((string) Str::ulid()))
        ->assertDispatched('toast', type: 'error');
});

/*
|--------------------------------------------------------------------------
| Estados del kit
|--------------------------------------------------------------------------
*/

it('muestra el estado vacío con CTA cuando el evento no tiene inscripciones', function () {
    $event = makeEntriesEvent();
    seedEntryCategories($event);

    Livewire::test(EntryManager::class, ['event' => $event->ulid])
        ->assertSee('Sin inscritos')
        ->assertSee('Nueva inscripción');
});
