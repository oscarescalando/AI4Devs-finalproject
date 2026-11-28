<?php

declare(strict_types=1);

use App\Livewire\Events\EventList;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Wave;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * ISSUE-160 fase 1 (TQ-025) — CRUD de eventos y carreras: render por rol,
 * creación con carreras y slug autogenerado, validación de servidor pintada
 * bajo el campo, edición con alta/baja de carreras (bloqueada con
 * inscripciones u oleadas), búsqueda, orden por lista blanca y contadores
 * del listado. Sin borrado de eventos (DEFER de TQ-025): se cierran.
 */
uses(RefreshDatabase::class);

function eventsAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function makeCrudEvent(string $name, string $date = '2026-12-05', string $status = 'registration'): Event
{
    return Event::query()->create([
        'name' => $name,
        'slug' => Str::slug($name),
        'date' => $date,
        'timezone' => 'America/Caracas',
        'status' => $status,
    ]);
}

/**
 * Inscripción mínima sobre la carrera (person XOR equipo, dorsales únicos).
 */
function seedEntryOnRace(Event $event, Race $race, int $bib): Entry
{
    $category = Category::query()->firstOrCreate(
        ['event_id' => $event->id, 'name' => 'General'],
        ['for_teams' => false, 'min_age' => 18, 'max_age' => null],
    );
    $wave = Wave::query()->firstOrCreate(
        ['race_id' => $race->id, 'name' => 'Única'],
        ['scheduled_start_at' => null, 'actual_start_at' => null],
    );

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => Person::factory()->create()->id,
        'team_id' => null,
        'bib' => $bib,
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

it('muestra la lista al organizador y al superadmin', function (string $role) {
    makeCrudEvent('VuelaCaracas 21K');

    $this->actingAs(eventsAccount($role))
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('VuelaCaracas 21K');
})->with(['organizer', 'superadmin']);

it('rechaza con 403 a roles sin events.view', function () {
    $this->actingAs(eventsAccount('timekeeper'))
        ->get(route('events.index'))
        ->assertForbidden();
});

it('el CTA de crear y el lápiz solo aparecen con events.manage', function () {
    makeCrudEvent('Evento Matriz');

    $organizer = (string) $this->actingAs(eventsAccount('organizer'))
        ->get(route('events.index'))
        ->getContent();

    $admin = (string) $this->actingAs(eventsAccount('admin'))
        ->get(route('events.index'))
        ->getContent();

    // El organizador gestiona; el admin solo lee (events.view sin manage).
    expect($organizer)->toContain('wire:click="startCreate"')->toContain('startEdit(');
    expect($admin)->not->toContain('wire:click="startCreate"')->not->toContain('startEdit(');
});

it('el servidor rechaza guardar sin events.manage aunque la UI se salte', function () {
    $this->actingAs(eventsAccount('admin'));

    Livewire::test(EventList::class)
        ->set('form.name', 'Evento Ilegal')
        ->set('form.slug', 'evento-ilegal')
        ->set('form.date', '2026-12-05')
        ->call('save')
        ->assertForbidden();

    $this->assertDatabaseMissing('events', ['slug' => 'evento-ilegal']);
});

/*
|--------------------------------------------------------------------------
| Crear (evento + carreras, slug autogenerado, estado inicial)
|--------------------------------------------------------------------------
*/

it('crea un evento con dos carreras y el slug autogenerado desde el nombre', function () {
    $this->actingAs(eventsAccount('organizer'));

    Livewire::test(EventList::class)
        ->set('form.name', 'Carrera de la Montaña')
        ->set('form.slug', '') // vacío: se deriva del nombre
        ->set('form.date', '2026-12-05')
        ->set('races', [['id' => null, 'name' => '21K'], ['id' => null, 'name' => 'Trail 12K']])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-event-modal')
        ->assertSet('editingUlid', null);

    $event = Event::query()->where('slug', 'carrera-de-la-montana')->first();

    expect($event)->not->toBeNull()
        ->and($event->name)->toBe('Carrera de la Montaña')
        ->and($event->date->format('Y-m-d'))->toBe('2026-12-05')
        // El alta no envía estado: nace con el default del esquema ('live').
        ->and($event->status)->toBe('live')
        ->and($event->timezone)->toBe('America/Caracas')
        ->and($event->races()->orderBy('id')->pluck('name')->all())->toBe(['21K', 'Trail 12K']);
});

it('respeta el slug escrito por el usuario', function () {
    $this->actingAs(eventsAccount('organizer'));

    Livewire::test(EventList::class)
        ->set('form.name', 'Evento Manual')
        ->set('form.slug', 'mi-slug-manual')
        ->set('form.date', '2026-12-05')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('events', ['name' => 'Evento Manual', 'slug' => 'mi-slug-manual']);
});

it('rechaza un slug duplicado con error bajo el campo y sin crear nada', function () {
    $this->actingAs(eventsAccount('organizer'));
    makeCrudEvent('Evento Original'); // slug evento-original

    Livewire::test(EventList::class)
        ->set('form.name', 'Evento Duplicado')
        ->set('form.slug', 'evento-original')
        ->set('form.date', '2026-12-05')
        ->set('races', [['id' => null, 'name' => '10K']])
        ->call('save')
        ->assertHasErrors(['form.slug'])
        ->assertSee('ya está en uso');

    expect(Event::query()->count())->toBe(1)
        ->and(Race::query()->count())->toBe(0);
});

it('rechaza un slug que no es kebab', function () {
    $this->actingAs(eventsAccount('organizer'));

    Livewire::test(EventList::class)
        ->set('form.name', 'Evento Válido')
        ->set('form.slug', 'Mi Slug')
        ->set('form.date', '2026-12-05')
        ->call('save')
        ->assertHasErrors(['form.slug']);
});

it('pinta el error bajo la fila del repeater cuando una carrera queda vacía', function () {
    $this->actingAs(eventsAccount('organizer'));

    Livewire::test(EventList::class)
        ->set('form.name', 'Evento Con Vacía')
        ->set('form.date', '2026-12-05')
        ->set('races', [['id' => null, 'name' => ''], ['id' => null, 'name' => '21K']])
        ->call('save')
        ->assertHasErrors(['races.0.name'])
        ->assertSee('obligatorio');
});

/*
|--------------------------------------------------------------------------
| Editar (carga, cambios de evento y de carreras)
|--------------------------------------------------------------------------
*/

it('carga el evento y sus carreras al editar', function () {
    $event = makeCrudEvent('VuelaCaracas 21K');
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);

    Livewire::test(EventList::class)
        ->call('startEdit', $event->ulid)
        ->assertSet('editingUlid', $event->ulid)
        ->assertSet('form.name', 'VuelaCaracas 21K')
        ->assertSet('form.slug', 'vuelacaracas-21k')
        ->assertSet('form.status', 'registration')
        ->assertSet('races.0.id', (string) $race->id)
        ->assertSet('races.0.name', '21K')
        ->assertDispatched('open-event-modal');
});

it('edita nombre, estado y fecha del evento', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('VuelaCaracas 21K');

    Livewire::test(EventList::class)
        ->call('startEdit', $event->ulid)
        ->set('form.name', 'VuelaCaracas 21K Edición 2027')
        ->set('form.date', '2027-01-10')
        ->set('form.status', 'finished')
        ->set('races', [['id' => null, 'name' => '21K']])
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->name)->toBe('VuelaCaracas 21K Edición 2027')
        ->and($event->slug)->toBe('vuelacaracas-21k')
        ->and($event->date->format('Y-m-d'))->toBe('2027-01-10')
        ->and($event->status)->toBe('finished');
});

it('añade y renombra carreras manteniendo las existentes', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('Evento Mixto');
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);

    Livewire::test(EventList::class)
        ->call('startEdit', $event->ulid)
        ->set('races', [
            ['id' => (string) $race->id, 'name' => '21K Elite'],
            ['id' => null, 'name' => '10K'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->races()->orderBy('id')->pluck('name')->all())->toBe(['21K Elite', '10K'])
        ->and($event->races()->count())->toBe(2)
        // Upsert por id: no se duplicó la carrera existente.
        ->and(Race::query()->where('event_id', $event->id)->where('name', '21K')->exists())->toBeFalse();
});

it('retira una carrera sin inscripciones', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('Evento Retiro');
    $queda = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $sale = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);

    Livewire::test(EventList::class)
        ->call('startEdit', $event->ulid)
        ->set('races', [['id' => (string) $queda->id, 'name' => '21K']])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($event->races()->count())->toBe(1)
        ->and(Race::query()->whereKey($sale->id)->exists())->toBeFalse();
});

it('no retira una carrera con inscripciones: error de negocio y sin cambios', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('Evento Con Inscritos');
    $queda = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $sale = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    seedEntryOnRace($event, $sale, 101);

    Livewire::test(EventList::class)
        ->call('startEdit', $event->ulid)
        ->set('races', [['id' => (string) $queda->id, 'name' => '21K']])
        ->call('save')
        ->assertDispatched('toast', type: 'error')
        ->assertNotDispatched('close-event-modal');

    // La transacción se revirtió: nada cambió en el evento ni en sus carreras.
    expect($event->races()->pluck('name')->all())->toBe(['21K', '10K'])
        ->and($event->refresh()->name)->toBe('Evento Con Inscritos')
        ->and(Entry::query()->count())->toBe(1);
});

it('tampoco retira una carrera con oleadas configuradas', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('Evento Con Oleadas');
    $sale = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    Wave::query()->create(['race_id' => $sale->id, 'name' => 'Única']);

    Livewire::test(EventList::class)
        ->call('startEdit', $event->ulid)
        ->set('races', [])
        ->call('save')
        ->assertDispatched('toast', type: 'error');

    expect(Race::query()->whereKey($sale->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Hub → editor (enlace con ?edit=)
|--------------------------------------------------------------------------
*/

it('el hub enlaza al editor para quien tiene events.manage', function () {
    $event = makeCrudEvent('VuelaCaracas 21K', status: 'live');

    $html = (string) $this->actingAs(eventsAccount('organizer'))
        ->get(route('events.show', $event->ulid))
        ->getContent();

    expect($html)->toContain(route('events.index', ['edit' => $event->ulid]));

    $htmlAdmin = (string) $this->actingAs(eventsAccount('admin'))
        ->get(route('events.show', $event->ulid))
        ->getContent();

    expect($htmlAdmin)->not->toContain('?edit='.$event->ulid);
});

it('el enlace del hub abre el modal de edición precargado', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('VuelaCaracas 21K');
    Race::query()->create(['event_id' => $event->id, 'name' => '21K']);

    $html = (string) $this->get(route('events.index', ['edit' => $event->ulid]))->getContent();

    expect($html)
        ->toContain('eventModal: true')
        ->toContain('VuelaCaracas 21K')
        ->toContain('21K');
});

/*
|--------------------------------------------------------------------------
| Tabla: búsqueda, orden por lista blanca, contadores y paginación
|--------------------------------------------------------------------------
*/

it('la búsqueda filtra por nombre y viaja en la query string', function () {
    makeCrudEvent('Corrida del Ávila');
    makeCrudEvent('Trail Merideño');

    Livewire::test(EventList::class)
        ->set('q', 'Ávila')
        ->assertSee('Corrida del Ávila')
        ->assertDontSee('Trail Merideño');

    Livewire::withQueryParams(['q' => 'Merideño'])
        ->test(EventList::class)
        ->assertSee('Trail Merideño')
        ->assertDontSee('Corrida del Ávila');
});

it('ordena por nombre o fecha y ignora columnas fuera de la lista blanca', function () {
    makeCrudEvent('Zeta Run', '2026-01-01');
    makeCrudEvent('Alfa Run', '2027-06-15');

    // Default: fecha descendente (el más próximo primero queda último aquí
    // porque 2027 > 2026, así que Alfa Run aparece primero).
    Livewire::test(EventList::class)
        ->assertSeeInOrder(['Alfa Run', 'Zeta Run']);

    // Nombre ascendente desde la query string.
    Livewire::withQueryParams(['sort' => 'name', 'direction' => 'asc'])
        ->test(EventList::class)
        ->assertSeeInOrder(['Alfa Run', 'Zeta Run']);

    // Nombre descendente: orden inverso.
    Livewire::withQueryParams(['sort' => 'name', 'direction' => 'desc'])
        ->test(EventList::class)
        ->assertSeeInOrder(['Zeta Run', 'Alfa Run']);

    // ?sort=password no debe llegar al SQL: cae al orden por defecto.
    Livewire::withQueryParams(['sort' => 'password', 'direction' => 'asc'])
        ->test(EventList::class)
        ->assertSeeInOrder(['Alfa Run', 'Zeta Run']);
});

it('muestra los contadores de carreras e inscritos de cada evento', function () {
    $this->actingAs(eventsAccount('organizer'));
    $event = makeCrudEvent('Evento Contadores');

    $bib = 101;
    foreach (['21K', '10K', 'Trail 12K'] as $name) {
        $race = Race::query()->create(['event_id' => $event->id, 'name' => $name]);
        if ($name !== 'Trail 12K') {
            // 5 inscritos repartidos entre 21K y 10K (dorsales únicos).
            foreach (range(1, 5) as $i) {
                seedEntryOnRace($event, $race, $bib++);
            }
        }
    }

    $html = (string) $this->get(route('events.index'))->getContent();

    expect($html)
        ->toContain('<td class="td-right mono">3</td>')
        ->toContain('<td class="td-right mono">10</td>');
});

it('pagina de a 10 con más de 10 eventos', function () {
    foreach (range(1, 12) as $i) {
        // Fechas descendentes con el índice: Evento 01 es el más reciente y
        // abre la primera página bajo el orden por defecto (fecha desc).
        $date = (new DateTimeImmutable('2026-12-01'))
            ->modify('-'.($i - 1).' days')
            ->format('Y-m-d');
        makeCrudEvent(sprintf('Evento %02d', $i), $date);
    }

    $component = Livewire::test(EventList::class);

    $component
        ->assertSee('Evento 01')
        ->assertSee('Evento 10')
        ->assertDontSee('Evento 11')
        ->assertSeeHtml('wire:click="nextPage(\'page\')"');

    $component->call('nextPage')
        ->assertSet('paginators.page', 2)
        ->assertSee('Evento 11')
        ->assertSee('Evento 12')
        ->assertDontSee('Evento 01');
});

/*
|--------------------------------------------------------------------------
| Estados del kit
|--------------------------------------------------------------------------
*/

it('bloquea el doble envío y muestra esqueleto durante la consulta', function () {
    makeCrudEvent('Evento Cualquiera');

    Livewire::test(EventList::class)
        ->assertSeeHtml('wire:loading.attr="disabled"')
        ->assertSeeHtml('wire:loading.remove')
        ->assertSeeHtml('skeleton');
});

it('muestra el estado vacío con CTA cuando no hay eventos', function () {
    $this->actingAs(eventsAccount('organizer'));

    Livewire::test(EventList::class)
        ->assertSee('Aún no hay eventos')
        ->assertSee('Nuevo evento')
        ->assertDontSeeHtml('wire:confirm'); // sin borrado (DEFER de TQ-025)
});
