<?php

declare(strict_types=1);

use App\Livewire\Events\CategoryManager;
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
 * ISSUE-160 fase 2 (TQ-026) — Editor de categorías: render por rol, alta y
 * edición con las validaciones reales del esquema (name 50 único por evento,
 * for_teams, min/max 1–120 con max >= min y sin solapamiento dentro del
 * mismo género-prefijo del nombre — el esquema no tiene columna de género),
 * borrado bloqueado por inscripciones (FK restrict) y 403 sin events.manage.
 */
uses(RefreshDatabase::class);

function categoriesAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function makeCategoryEvent(string $name = 'VuelaCaracas 21K'): Event
{
    return Event::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower((string) Str::ulid()),
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'registration',
    ]);
}

function seedCategory(Event $event, string $name, ?int $min, ?int $max, bool $forTeams = false): Category
{
    return Category::query()->create([
        'event_id' => $event->id,
        'name' => $name,
        'for_teams' => $forTeams,
        'min_age' => $min,
        'max_age' => $max,
    ]);
}

/**
 * Inscripción mínima sobre una categoría (necesita carrera y oleada).
 */
function seedEntryOnCategory(Event $event, Category $category, int $bib): Entry
{
    $race = Race::query()->firstOrCreate(['event_id' => $event->id, 'name' => '21K']);
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

it('muestra el editor de categorías al organizador y al superadmin', function (string $role) {
    $event = makeCategoryEvent();

    $this->actingAs(categoriesAccount($role))
        ->get(route('events.categories', $event->ulid))
        ->assertOk()
        ->assertSee('Categorías')
        ->assertSee($event->name);
})->with(['organizer', 'superadmin']);

it('rechaza con 403 a roles sin events.manage', function () {
    $event = makeCategoryEvent();

    $this->actingAs(categoriesAccount('timekeeper'))
        ->get(route('events.categories', $event->ulid))
        ->assertForbidden();
});

it('el servidor rechaza guardar y eliminar sin events.manage aunque la UI se salte', function () {
    $event = makeCategoryEvent();
    $category = seedCategory($event, 'General', null, null);
    $this->actingAs(categoriesAccount('admin')); // events.view sin manage

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Ilegal')
        ->call('save')
        ->assertForbidden();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->call('deleteCategory', $category->ulid)
        ->assertForbidden();

    expect(Category::query()->count())->toBe(1);
});

it('devuelve 404 para un evento inexistente', function () {
    $this->actingAs(categoriesAccount('organizer'))
        ->get('/events/'.strtolower((string) Str::ulid()).'/categories')
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Listado (columnas reales del esquema)
|--------------------------------------------------------------------------
*/

it('lista las categorías con tipo, rango formateado e inscritos', function () {
    $event = makeCategoryEvent();
    $closed = seedCategory($event, 'M 20-29', 20, 29);
    seedCategory($event, 'Junior', 10, null);
    $teams = seedCategory($event, 'Relevo Mixto', 18, null, true);
    seedEntryOnCategory($event, $closed, 101);
    seedEntryOnCategory($event, $closed, 102);
    seedEntryOnCategory($event, $teams, 103);

    $html = (string) $this->actingAs(categoriesAccount('organizer'))
        ->get(route('events.categories', $event->ulid))
        ->getContent();

    expect($html)
        ->toContain('M 20-29')
        ->toContain('20–29')
        ->toContain('10+')
        ->toContain('Individual')
        ->toContain('Equipos')
        ->toContain('<td class="td-right mono">2</td>')
        ->toContain('<td class="td-right mono">1</td>')
        ->toContain('wire:confirm'); // destructiva con confirmación
});

/*
|--------------------------------------------------------------------------
| Crear (validaciones reales del esquema + coherencia de rangos)
|--------------------------------------------------------------------------
*/

it('crea una categoría con rango cerrado', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'M 30-39')
        ->set('form.min_age', '30')
        ->set('form.max_age', '39')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-category-modal');

    $category = Category::query()->where('event_id', $event->id)->sole();

    expect($category->name)->toBe('M 30-39')
        ->and($category->for_teams)->toBeFalse()
        ->and($category->min_age)->toBe(30)
        ->and($category->max_age)->toBe(39);
});

it('crea una categoría de equipos con rango abierto', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Relevo Mixto')
        ->set('form.for_teams', true)
        ->set('form.min_age', '18')
        ->call('save')
        ->assertHasNoErrors();

    $category = Category::query()->where('event_id', $event->id)->sole();

    expect($category->for_teams)->toBeTrue()
        ->and($category->min_age)->toBe(18)
        ->and($category->max_age)->toBeNull();
});

it('exige nombre y lo pinta bajo el campo', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertNotDispatched('close-category-modal');
});

it('rechaza un nombre duplicado en el mismo evento', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    seedCategory($event, 'Junior', 10, 19);

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Junior')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSee('ya está en uso');

    expect(Category::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('rechaza edades fuera de rango y máximos menores que el mínimo', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'M 50-40')
        ->set('form.min_age', '50')
        ->set('form.max_age', '40')
        ->call('save')
        ->assertHasErrors(['form.max_age']);

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'M 999')
        ->set('form.min_age', '999')
        ->call('save')
        ->assertHasErrors(['form.min_age']);

    expect(Category::query()->count())->toBe(0);
});

it('rechaza el solapamiento de rangos dentro del mismo género del nombre', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    seedCategory($event, 'M 20-29', 20, 29);

    // Mismo género «M»: 25 cae dentro de 20-29.
    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'M 25-34')
        ->set('form.min_age', '25')
        ->set('form.max_age', '34')
        ->call('save')
        ->assertHasErrors(['form.min_age'])
        ->assertSee('se solapa');

    // Rango abierto «M 18+» también cruza el 20-29.
    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'M Abierta')
        ->set('form.min_age', '18')
        ->call('save')
        ->assertHasErrors(['form.min_age']);

    expect(Category::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('permite rangos iguales en géneros distintos y en categorías sin género', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    seedCategory($event, 'M 20-29', 20, 29);

    // El esquema no tiene columna de género: F y «Junior» no se comparan con M.
    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'F 20-29')
        ->set('form.min_age', '20')
        ->set('form.max_age', '29')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Junior')
        ->set('form.min_age', '20')
        ->set('form.max_age', '29')
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::query()->where('event_id', $event->id)->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Editar
|--------------------------------------------------------------------------
*/

it('carga la categoría al editar y guarda sus cambios', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    $category = seedCategory($event, 'M 20-29', 20, 29);

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->call('startEdit', $category->ulid)
        ->assertSet('editingUlid', $category->ulid)
        ->assertSet('form.name', 'M 20-29')
        ->assertSet('form.min_age', 20)
        ->assertSet('form.max_age', 29)
        ->assertDispatched('open-category-modal');

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->call('startEdit', $category->ulid)
        ->set('form.name', 'M 20-34')
        ->set('form.max_age', '34')
        ->call('save')
        ->assertHasNoErrors();

    $category->refresh();

    expect($category->name)->toBe('M 20-34')
        ->and($category->max_age)->toBe(34);
});

it('al editar se ignora a sí misma en el solapamiento y en el nombre único', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    $category = seedCategory($event, 'M 20-29', 20, 29);

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->call('startEdit', $category->ulid)
        ->set('form.name', 'M 20-29')
        ->set('form.min_age', '20')
        ->set('form.max_age', '29')
        ->call('save')
        ->assertHasNoErrors();
});

it('no deja colar una categoría de otro evento por su ULID', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $mine = makeCategoryEvent('Evento Mío');
    $other = makeCategoryEvent('Evento Ajeno');
    $foreign = seedCategory($other, 'M 20-29', 20, 29);

    Livewire::test(CategoryManager::class, ['event' => $mine->ulid])
        ->call('startEdit', $foreign->ulid)
        ->assertDispatched('toast', type: 'error')
        ->assertSet('editingUlid', null);
});

/*
|--------------------------------------------------------------------------
| Borrar (bloqueado con inscripciones — FK restrict)
|--------------------------------------------------------------------------
*/

it('elimina una categoría sin inscripciones tras confirmar', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    $category = seedCategory($event, 'Junior', 10, 19);

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->call('deleteCategory', $category->ulid)
        ->assertDispatched('toast', type: 'success');

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('bloquea el borrado de una categoría con inscripciones con error de negocio', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();
    $category = seedCategory($event, 'M 20-29', 20, 29);
    seedEntryOnCategory($event, $category, 101);

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->call('deleteCategory', $category->ulid)
        ->assertDispatched('toast', type: 'error');

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue()
        ->and(Entry::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Estados del kit
|--------------------------------------------------------------------------
*/

it('muestra el estado vacío con CTA cuando el evento no tiene categorías', function () {
    $this->actingAs(categoriesAccount('organizer'));
    $event = makeCategoryEvent();

    Livewire::test(CategoryManager::class, ['event' => $event->ulid])
        ->assertSee('Aún no hay categorías')
        ->assertSee('Nueva categoría');
});
