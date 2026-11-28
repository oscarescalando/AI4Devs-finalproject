<?php

declare(strict_types=1);

use App\Livewire\Directory\TeamManager;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Race;
use App\Models\Region;
use App\Models\Team;
use App\Models\Wave;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * ISSUE-161 (TQ-028) — Directorio de equipos: render por rol, alta y
 * edición con organización por su ULID público (ADR-015), filtro ?org=,
 * búsqueda por nombre, borrado bloqueado por inscripciones (FK restrict)
 * y 403 sin directory.view.
 */
uses(RefreshDatabase::class);

function teamsAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function seedOrganization(string $name = 'Club Prueba'): Organization
{
    return Organization::query()->create([
        'region_id' => Region::factory()->create()->id,
        'name' => $name,
    ]);
}

function seedTeam(Organization $organization, string $name): Team
{
    return Team::query()->create([
        'organization_id' => $organization->id,
        'name' => $name,
    ]);
}

/**
 * Inscripción de equipo (person_id null + team_id, CHECK XOR del esquema):
 * solo para probar el bloqueo del borrado por FK restrict.
 */
function seedEntryForTeam(Team $team): Entry
{
    $event = Event::query()->create([
        'name' => 'Evento Equipos 21K',
        'slug' => 'evento-equipos-21k-'.Str::lower((string) Str::ulid()),
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'registration',
    ]);
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = Wave::query()->create(['race_id' => $race->id, 'name' => 'Única']);
    $category = Category::query()->create([
        'event_id' => $event->id,
        'name' => 'Relevo',
        'for_teams' => true,
        'min_age' => null,
        'max_age' => null,
    ]);

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => null,
        'team_id' => $team->id,
        'bib' => 201,
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
    $this->actingAs(teamsAccount('admin'));
    $organization = seedOrganization();
    seedTeam($organization, 'Equipo Relevo');

    Livewire::test(TeamManager::class)
        ->assertSee('Equipos')
        ->assertSee('Equipo Relevo')
        ->assertSee($organization->name)
        ->assertSee('Nuevo equipo');
});

it('la ruta responde 200 al administrador', function () {
    seedTeam(seedOrganization(), 'Equipo Ruta');

    $this->actingAs(teamsAccount('admin'))
        ->get(route('directory.teams'))
        ->assertOk()
        ->assertSee('Equipos');
});

it('rechaza con 403 a roles sin directory.view', function () {
    $this->actingAs(teamsAccount('timekeeper'))
        ->get(route('directory.teams'))
        ->assertForbidden();
});

it('redirige a los invitados al login', function () {
    $this->get(route('directory.teams'))->assertRedirect(route('login'));
});

it('respeta la matriz de acceso por rol', function (string $role, bool $conLecturaDirecta, int $status, bool $veBotonCrear) {
    seedTeam(seedOrganization(), 'Equipo Matriz');

    $account = Account::factory()->create();
    $account->assignRole($role);
    if ($conLecturaDirecta) {
        $account->givePermissionTo('directory.view');
    }

    $response = $this->actingAs($account)->get(route('directory.teams'));

    expect($response->status())->toBe($status);

    if ($status !== 200) {
        return;
    }

    $html = (string) $response->getContent();

    expect($html)->toContain('Equipo Matriz');

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

it('el servidor rechaza guardar sin permiso de escritura aunque la UI se salte', function () {
    $viewer = teamsAccount('organizer');
    $viewer->givePermissionTo('directory.view');

    $this->actingAs($viewer);
    $organization = seedOrganization();

    Livewire::test(TeamManager::class)
        ->set('form.name', 'Equipo Ilegal')
        ->set('form.organization_ulid', $organization->ulid)
        ->call('save')
        ->assertForbidden();

    $this->assertDatabaseMissing('teams', ['name' => 'Equipo Ilegal']);
});

/*
|--------------------------------------------------------------------------
| Validación de servidor y CRUD (vía Actions)
|--------------------------------------------------------------------------
*/

it('crea un equipo desde el formulario del modal', function () {
    $this->actingAs(teamsAccount('admin'));
    $organization = seedOrganization('Club Creador');

    Livewire::test(TeamManager::class)
        ->set('form.name', 'Equipo Nuevo')
        ->set('form.organization_ulid', $organization->ulid)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-team-modal')
        ->assertSet('editingUlid', null)
        ->assertSet('form.name', '');

    $this->assertDatabaseHas('teams', [
        'organization_id' => $organization->id,
        'name' => 'Equipo Nuevo',
    ]);
});

it('exige el nombre del equipo', function () {
    $this->actingAs(teamsAccount('admin'));
    $organization = seedOrganization();

    Livewire::test(TeamManager::class)
        ->set('form.name', '')
        ->set('form.organization_ulid', $organization->ulid)
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSee('El campo nombre es obligatorio.');
});

it('exige una organización existente', function () {
    $this->actingAs(teamsAccount('admin'));

    Livewire::test(TeamManager::class)
        ->set('form.name', 'Equipo Huérfano')
        ->set('form.organization_ulid', (string) Str::ulid())
        ->call('save')
        ->assertHasErrors(['form.organization_ulid']);

    $this->assertDatabaseMissing('teams', ['name' => 'Equipo Huérfano']);
});

it('carga los datos al editar y actualiza nombre y organización', function () {
    $this->actingAs(teamsAccount('admin'));
    $vieja = seedOrganization('Club Viejo');
    $nueva = seedOrganization('Club Nuevo');
    $team = seedTeam($vieja, 'Equipo Viejo');

    Livewire::test(TeamManager::class)
        ->call('startEdit', $team->ulid)
        ->assertSet('editingUlid', $team->ulid)
        ->assertSet('form.name', 'Equipo Viejo')
        ->assertSet('form.organization_ulid', $vieja->ulid)
        ->assertDispatched('open-team-modal');

    Livewire::test(TeamManager::class)
        ->call('startEdit', $team->ulid)
        ->set('form.name', 'Equipo Renombrado')
        ->set('form.organization_ulid', $nueva->ulid)
        ->call('save')
        ->assertHasNoErrors();

    expect($team->refresh()->name)->toBe('Equipo Renombrado')
        ->and($team->organization_id)->toBe($nueva->id);
});

/*
|--------------------------------------------------------------------------
| Borrados: bloqueado con inscripciones, permitido sin ellas
|--------------------------------------------------------------------------
*/

it('bloquea el borrado de un equipo con inscripciones', function () {
    $this->actingAs(teamsAccount('admin'));
    $team = seedTeam(seedOrganization(), 'Equipo Inscrito');
    seedEntryForTeam($team);

    Livewire::test(TeamManager::class)
        ->call('deleteTeam', $team->ulid)
        ->assertDispatched('toast', type: 'error');

    $this->assertDatabaseHas('teams', ['id' => $team->id]);
});

it('elimina un equipo sin inscripciones', function () {
    $this->actingAs(teamsAccount('admin'));
    $team = seedTeam(seedOrganization(), 'Equipo Libre');

    Livewire::test(TeamManager::class)
        ->call('deleteTeam', $team->ulid)
        ->assertDispatched('toast', type: 'success');

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

/*
|--------------------------------------------------------------------------
| Tabla: búsqueda parcial y filtro por organización (?org=)
|--------------------------------------------------------------------------
*/

it('la búsqueda parcial filtra por nombre', function () {
    $organization = seedOrganization();
    seedTeam($organization, 'Ávila Runners');
    seedTeam($organization, 'Zulianos Trail');

    Livewire::test(TeamManager::class)
        ->set('q', 'Ávila')
        ->assertSee('Ávila Runners')
        ->assertDontSee('Zulianos');
});

it('filtra por organización desde la query string (?org=)', function () {
    $alfa = seedOrganization('Club Alfa');
    $beta = seedOrganization('Club Beta');
    seedTeam($alfa, 'Equipo de Alfa');
    seedTeam($beta, 'Equipo de Beta');

    Livewire::withQueryParams(['org' => $alfa->ulid])
        ->test(TeamManager::class)
        ->assertSee('Equipo de Alfa')
        ->assertDontSee('Equipo de Beta');

    Livewire::test(TeamManager::class)
        ->set('org', $beta->ulid)
        ->assertSee('Equipo de Beta')
        ->assertDontSee('Equipo de Alfa');
});

it('pagina de a 10 equipos', function () {
    $organization = seedOrganization();
    foreach (range(1, 15) as $i) {
        seedTeam($organization, sprintf('Equipo %02d', $i));
    }

    Livewire::test(TeamManager::class)
        ->assertSee('Equipo 01')
        ->assertSee('Equipo 10')
        ->assertDontSee('Equipo 11');

    Livewire::test(TeamManager::class)
        ->call('nextPage')
        ->assertSee('Equipo 11')
        ->assertDontSee('Equipo 01');
});
