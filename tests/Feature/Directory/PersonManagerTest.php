<?php

declare(strict_types=1);

use App\Livewire\Directory\PersonManager;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Wave;
use App\Support\FormatDate;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ISSUE-161 (TQ-028) — Directorio de personas: render por rol, documento
 * único global con formato V-/E- + 6-8 dígitos, nacimiento pasado y
 * coherente (edad 5-120), PII enmascarada en el listado (documento
 * enmascarado, nacimiento ausente y edad calculada), borrado bloqueado por
 * inscripciones/cuenta y búsqueda parcial por nombre o documento.
 */
uses(RefreshDatabase::class);

function personsAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

/**
 * Organizador con `directory.view` concedido directo (ISSUE-116a): lee el
 * directorio pero no escribe — directory.write queda para los roles
 * administradores (convención en AppServiceProvider).
 */
function personsOrganizerViewer(): Account
{
    $account = Account::factory()->create();
    $account->assignRole('organizer');
    $account->givePermissionTo('directory.view');

    return $account;
}

function seedPerson(array $attributes = []): Person
{
    return Person::query()->create(array_merge([
        'document' => 'V-'.random_int(1000000, 9999999),
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'birthdate' => '1990-05-10',
        'gender' => 'F',
    ], $attributes));
}

/**
 * Inscripción mínima sobre una persona (necesita el evento, su carrera,
 * oleada y categoría): solo para probar el bloqueo del borrado por FK.
 */
function seedEntryForPerson(Person $person): Entry
{
    $event = Event::query()->create([
        'name' => 'Evento Bloqueos 10K',
        'slug' => 'evento-bloqueos-10k-'.Str::lower((string) Str::ulid()),
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'registration',
    ]);
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    $wave = Wave::query()->create(['race_id' => $race->id, 'name' => 'Única']);
    $category = Category::query()->create([
        'event_id' => $event->id,
        'name' => 'Libre',
        'for_teams' => false,
        'min_age' => null,
        'max_age' => null,
    ]);

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => 101,
    ]);
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Acceso (la UI oculta; el servidor autoriza — ADR-011)
|--------------------------------------------------------------------------
*/

it('muestra la lista y el botón de crear para el administrador', function () {
    $this->actingAs(personsAccount('admin'));
    seedPerson(['first_name' => 'María', 'last_name' => 'González']);

    Livewire::test(PersonManager::class)
        ->assertSee('Personas')
        ->assertSee('María')
        ->assertSee('Nueva persona');
});

it('la ruta responde 200 al administrador', function () {
    seedPerson();

    $this->actingAs(personsAccount('admin'))
        ->get(route('directory.persons'))
        ->assertOk()
        ->assertSee('Personas');
});

it('rechaza con 403 a roles sin directory.view', function () {
    $this->actingAs(personsAccount('timekeeper'))
        ->get(route('directory.persons'))
        ->assertForbidden();
});

it('redirige a los invitados al login', function () {
    $this->get(route('directory.persons'))->assertRedirect(route('login'));
});

it('respeta la matriz de acceso por rol', function (string $role, bool $conLecturaDirecta, int $status, bool $veBotonCrear) {
    seedPerson(['first_name' => 'Matriz', 'last_name' => 'Prueba']);

    $account = Account::factory()->create();
    $account->assignRole($role);
    if ($conLecturaDirecta) {
        $account->givePermissionTo('directory.view');
    }

    $response = $this->actingAs($account)->get(route('directory.persons'));

    expect($response->status())->toBe($status);

    if ($status !== 200) {
        return;
    }

    $html = (string) $response->getContent();

    expect($html)->toContain('Matriz');

    $veBotonCrear
        ? expect($html)->toContain('wire:click="startCreate"')
        : expect($html)->not->toContain('wire:click="startCreate"');
})->with([
    'admin ve y opera' => ['admin', false, 200, true],
    'superadmin ve y opera' => ['superadmin', false, 200, true],
    'organizer con directory.view lee sin botón' => ['organizer', true, 200, false],
    'timekeeper sin acceso' => ['timekeeper', false, 403, false],
    'athlete sin acceso' => ['athlete', false, 403, false],
]);

it('quien solo lee no ve las acciones de editar ni eliminar', function () {
    seedPerson(['first_name' => 'Solo', 'last_name' => 'Lectura']);

    $html = (string) $this->actingAs(personsOrganizerViewer())
        ->get(route('directory.persons'))
        ->getContent();

    expect($html)
        ->not->toContain('startEdit(')
        ->not->toContain('deletePerson(')
        ->not->toContain('¿Eliminar');
});

it('el servidor rechaza guardar sin permiso de escritura aunque la UI se salte', function () {
    $this->actingAs(personsOrganizerViewer());

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-5987654')
        ->set('form.first_name', 'Ilegal')
        ->set('form.last_name', 'Prueba')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'M')
        ->call('save')
        ->assertForbidden();

    $this->assertDatabaseMissing('persons', ['first_name' => 'Ilegal']);
});

it('el servidor rechaza eliminar sin permiso de escritura', function () {
    $this->actingAs(personsOrganizerViewer());
    $person = seedPerson();

    Livewire::test(PersonManager::class)
        ->call('deletePerson', $person->ulid)
        ->assertForbidden();

    $this->assertDatabaseHas('persons', ['id' => $person->id]);
});

/*
|--------------------------------------------------------------------------
| Validación de servidor (única fuente de verdad)
|--------------------------------------------------------------------------
*/

it('crea una persona desde el formulario del modal', function () {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-1987654')
        ->set('form.first_name', 'Nueva')
        ->set('form.last_name', 'Persona')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'F')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-person-modal')
        ->assertSet('editingUlid', null)
        ->assertSet('form.document', '');

    $this->assertDatabaseHas('persons', [
        'document' => 'V-1987654',
        'first_name' => 'Nueva',
        'last_name' => 'Persona',
        'birthdate' => '1990-05-10',
        'gender' => 'F',
    ]);
});

it('rechaza el documento duplicado bajo el campo y no crea nada', function () {
    $this->actingAs(personsAccount('admin'));
    seedPerson(['document' => 'V-1122233']);

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-1122233')
        ->set('form.first_name', 'Duplicada')
        ->set('form.last_name', 'Prueba')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'M')
        ->call('save')
        ->assertHasErrors(['form.document'])
        ->assertSee('Ya existe una persona registrada con ese documento.');

    expect(Person::query()->where('document', 'V-1122233')->count())->toBe(1);
    $this->assertDatabaseMissing('persons', ['first_name' => 'Duplicada']);
});

it('rechaza formatos de documento inválidos', function (string $document) {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', $document)
        ->set('form.first_name', 'Formato')
        ->set('form.last_name', 'Prueba')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'M')
        ->call('save')
        ->assertHasErrors(['form.document'])
        ->assertSee('El documento debe tener formato V- o E- seguido de 6 a 8 dígitos.');

    $this->assertDatabaseMissing('persons', ['first_name' => 'Formato']);
})->with([
    'muy corto' => ['V-12345'],
    'muy largo' => ['V-123456789'],
    'prefijo inválido' => ['X-1234567'],
    'sin prefijo' => ['1234567'],
]);

it('acepta el documento E- y normaliza minúsculas a mayúsculas', function () {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', 'e-2876543')
        ->set('form.first_name', 'Extranjera')
        ->set('form.last_name', 'Prueba')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'F')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('persons', ['document' => 'E-2876543']);
});

it('exige nombres y apellidos', function () {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-3987654')
        ->set('form.first_name', '')
        ->set('form.last_name', '')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'M')
        ->call('save')
        ->assertHasErrors(['form.first_name', 'form.last_name']);
});

it('rechaza la fecha de nacimiento no pasada', function () {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-4987654')
        ->set('form.first_name', 'Futuro')
        ->set('form.last_name', 'Prueba')
        ->set('form.birthdate', now()->addDay()->toDateString())
        ->set('form.gender', 'M')
        ->call('save')
        ->assertHasErrors(['form.birthdate'])
        ->assertSee('La fecha de nacimiento debe ser una fecha pasada.');
});

it('rechaza edades fuera del rango 5-120', function (string $birthdate) {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-5987654')
        ->set('form.first_name', 'Edad')
        ->set('form.last_name', 'Fuera')
        ->set('form.birthdate', $birthdate)
        ->set('form.gender', 'M')
        ->call('save')
        ->assertHasErrors(['form.birthdate'])
        ->assertSee('entre 5 y 120 años');
})->with([
    'demasiado joven' => [now()->subYears(4)->toDateString()],
    'demasiado viejo' => [now()->subYears(121)->toDateString()],
]);

it('rechaza un género fuera del esquema', function () {
    $this->actingAs(personsAccount('admin'));

    Livewire::test(PersonManager::class)
        ->set('form.document', 'V-6987654')
        ->set('form.first_name', 'Género')
        ->set('form.last_name', 'Inválido')
        ->set('form.birthdate', '1990-05-10')
        ->set('form.gender', 'Z')
        ->call('save')
        ->assertHasErrors(['form.gender']);

    $this->assertDatabaseMissing('persons', ['first_name' => 'Género']);
});

it('carga los datos completos al editar y actualiza sin chocar con su propio documento', function () {
    $this->actingAs(personsAccount('admin'));
    $person = seedPerson(['document' => 'V-7987654', 'first_name' => 'Vieja']);

    Livewire::test(PersonManager::class)
        ->call('startEdit', $person->ulid)
        ->assertSet('editingUlid', $person->ulid)
        ->assertSet('form.document', 'V-7987654')
        ->assertSet('form.birthdate', '1990-05-10')
        ->assertDispatched('open-person-modal');

    Livewire::test(PersonManager::class)
        ->call('startEdit', $person->ulid)
        ->set('form.first_name', 'Renombrada')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->refresh()->first_name)->toBe('Renombrada');
});

/*
|--------------------------------------------------------------------------
| PII en el listado (RNF-13): documento enmascarado, nacimiento ausente
|--------------------------------------------------------------------------
*/

it('muestra el documento enmascarado y jamás el completo', function () {
    $this->actingAs(personsAccount('admin'));
    seedPerson(['document' => 'V-1987654', 'first_name' => 'Enmascarada', 'last_name' => 'Prueba']);

    Livewire::test(PersonManager::class)
        ->assertDontSee('V-1987654')
        ->assertSee('V-1••••54');
});

it('no muestra la fecha de nacimiento en la lista pero sí la edad calculada', function () {
    $this->actingAs(personsAccount('admin'));
    $person = seedPerson(['birthdate' => '1990-05-10']);
    $edad = (string) Carbon::parse('1990-05-10')->age;

    Livewire::test(PersonManager::class)
        ->assertDontSee('1990-05-10')
        ->assertDontSee(FormatDate::date($person->birthdate))
        ->assertSee($edad);
});

/*
|--------------------------------------------------------------------------
| Borrados: bloqueados por FK (inscripciones/cuenta), permitidos sin ellas
|--------------------------------------------------------------------------
*/

it('bloquea el borrado de una persona con inscripciones', function () {
    $this->actingAs(personsAccount('admin'));
    $person = seedPerson(['first_name' => 'Con', 'last_name' => 'Inscripción']);
    seedEntryForPerson($person);

    Livewire::test(PersonManager::class)
        ->call('deletePerson', $person->ulid)
        ->assertDispatched('toast', type: 'error');

    $this->assertDatabaseHas('persons', ['id' => $person->id]);
});

it('bloquea el borrado de una persona con cuenta de acceso', function () {
    $this->actingAs(personsAccount('admin'));
    $person = seedPerson(['first_name' => 'Con', 'last_name' => 'Cuenta']);
    Account::factory()->create(['person_id' => $person->id]);

    Livewire::test(PersonManager::class)
        ->call('deletePerson', $person->ulid)
        ->assertDispatched('toast', type: 'error');

    $this->assertDatabaseHas('persons', ['id' => $person->id]);
    $this->assertDatabaseHas('accounts', ['person_id' => $person->id]);
});

it('elimina una persona sin inscripciones ni cuenta', function () {
    $this->actingAs(personsAccount('admin'));
    $person = seedPerson();

    Livewire::test(PersonManager::class)
        ->call('deletePerson', $person->ulid)
        ->assertDispatched('toast', type: 'success');

    $this->assertDatabaseMissing('persons', ['id' => $person->id]);
});

/*
|--------------------------------------------------------------------------
| Tabla: búsqueda parcial y paginación
|--------------------------------------------------------------------------
*/

it('la búsqueda parcial filtra por nombre', function () {
    seedPerson(['first_name' => 'Corredora', 'last_name' => 'Ávila']);
    seedPerson(['first_name' => 'Otra', 'last_name' => 'Zuliana']);

    Livewire::test(PersonManager::class)
        ->set('q', 'Ávila')
        ->assertSee('Corredora')
        ->assertDontSee('Zuliana');
});

it('la búsqueda parcial filtra por documento aunque se pinta enmascarado', function () {
    seedPerson(['document' => 'V-2998877', 'first_name' => 'Documento', 'last_name' => 'Buscado']);
    seedPerson(['document' => 'V-3112244', 'first_name' => 'Documento', 'last_name' => 'Otro']);

    Livewire::test(PersonManager::class)
        ->set('q', '2998')
        ->assertSee('Buscado')
        ->assertDontSee('Otro')
        ->assertDontSee('V-2998877');
});

it('pagina de a 10 personas', function () {
    foreach (range(1, 15) as $i) {
        seedPerson(['first_name' => 'Persona', 'last_name' => sprintf('%02d', $i)]);
    }

    Livewire::test(PersonManager::class)
        ->assertSee('Persona 01')
        ->assertSee('Persona 10')
        ->assertDontSee('Persona 11');

    Livewire::test(PersonManager::class)
        ->call('nextPage')
        ->assertSee('Persona 11')
        ->assertDontSee('Persona 01');
});
