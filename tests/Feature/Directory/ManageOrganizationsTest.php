<?php

declare(strict_types=1);

use App\Livewire\Directory\ManageOrganizations;
use App\Models\Account;
use App\Models\Organization;
use App\Models\Region;
use App\Models\Team;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ISSUE-110 (TQ-003) — CRUD demo Organizaciones: render por rol, validación
 * de servidor pintada bajo el campo, orden solo por lista blanca, búsqueda,
 * paginación por query string, confirmación en destructivas y bloqueo de
 * doble envío.
 */
uses(RefreshDatabase::class);

function directoryAdmin(): Account
{
    $account = Account::factory()->create();
    $account->assignRole('admin');

    return $account;
}

function directoryTimekeeper(): Account
{
    $account = Account::factory()->create();
    $account->assignRole('timekeeper');

    return $account;
}

function seedOrganizations(int $count): void
{
    $region = Region::factory()->create();

    foreach (range(1, $count) as $i) {
        Organization::factory()->create([
            'region_id' => $region->id,
            'name' => sprintf('Club %02d', $i),
        ]);
    }
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
    $this->actingAs(directoryAdmin());
    Organization::factory()->create(['name' => 'Club Alfa']);

    Livewire::test(ManageOrganizations::class)
        ->assertSee('Organizaciones')
        ->assertSee('Nombre')
        ->assertSee('Club Alfa')
        ->assertSee('Nueva organización');
});

it('la ruta responde 200 al administrador', function () {
    Organization::factory()->create();

    $this->actingAs(directoryAdmin())
        ->get(route('directory.organizations'))
        ->assertOk()
        ->assertSee('Organizaciones');
});

it('rechaza con 403 a roles sin directory.view', function () {
    $this->actingAs(directoryTimekeeper())
        ->get(route('directory.organizations'))
        ->assertForbidden();
});

it('redirige a los invitados al login', function () {
    $this->get(route('directory.organizations'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Validación de servidor (única fuente de verdad)
|--------------------------------------------------------------------------
*/

it('pinta el error del servidor bajo el campo cuando falta el nombre', function () {
    Livewire::test(ManageOrganizations::class)
        ->set('form.name', '')
        ->set('form.region_id', (string) Region::factory()->create()->id)
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSee('El campo nombre es obligatorio.');
});

it('rechaza nombres de más de 150 caracteres', function () {
    Livewire::test(ManageOrganizations::class)
        ->set('form.name', str_repeat('a', 151))
        ->set('form.region_id', (string) Region::factory()->create()->id)
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSee('no debe superar 150 caracteres');
});

it('rechaza una región inexistente', function () {
    Livewire::test(ManageOrganizations::class)
        ->set('form.name', 'Club Válido')
        ->set('form.region_id', '999999')
        ->call('save')
        ->assertHasErrors(['form.region_id'])
        ->assertSee('El campo región seleccionado no existe.');
});

/*
|--------------------------------------------------------------------------
| Crear · editar · eliminar (vía Actions)
|--------------------------------------------------------------------------
*/

it('crea una organización desde el formulario del modal', function () {
    $region = Region::factory()->create();

    Livewire::test(ManageOrganizations::class)
        ->set('form.name', 'Club Nuevo')
        ->set('form.region_id', (string) $region->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-org-modal')
        ->assertSet('editingUlid', null)
        ->assertSet('form.name', '');

    $this->assertDatabaseHas('organizations', [
        'name' => 'Club Nuevo',
        'region_id' => $region->id,
    ]);
});

it('carga los datos de la organización al editar', function () {
    $region = Region::factory()->create();
    $organization = Organization::factory()->create([
        'region_id' => $region->id,
        'name' => 'Club Viejo',
    ]);

    Livewire::test(ManageOrganizations::class)
        ->call('startEdit', $organization->ulid)
        ->assertSet('editingUlid', $organization->ulid)
        ->assertSet('form.name', 'Club Viejo')
        ->assertSet('form.region_id', (string) $region->id)
        ->assertDispatched('open-org-modal');
});

it('actualiza una organización existente', function () {
    $organization = Organization::factory()->create(['name' => 'Club Viejo']);
    $region = Region::factory()->create();

    Livewire::test(ManageOrganizations::class)
        ->call('startEdit', $organization->ulid)
        ->set('form.name', 'Club Renombrado')
        ->set('form.region_id', (string) $region->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($organization->refresh()->name)->toBe('Club Renombrado')
        ->and($organization->region_id)->toBe($region->id);
});

it('elimina una organización y avisa con toast', function () {
    $organization = Organization::factory()->create();

    Livewire::test(ManageOrganizations::class)
        ->call('deleteOrganization', $organization->ulid)
        ->assertDispatched('toast');

    $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
});

it('el botón de eliminar exige confirmación previa', function () {
    Organization::factory()->create();

    Livewire::test(ManageOrganizations::class)
        ->assertSeeHtml('wire:confirm="¿Eliminar');
});

it('no elimina una organización con equipos y avisa del error', function () {
    $organization = Organization::factory()->create();
    Team::query()->create(['organization_id' => $organization->id, 'name' => 'Equipo Relevo']);

    Livewire::test(ManageOrganizations::class)
        ->call('deleteOrganization', $organization->ulid)
        ->assertDispatched('toast', type: 'error');

    $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
});

/*
|--------------------------------------------------------------------------
| Tabla: búsqueda, orden por lista blanca, paginación
|--------------------------------------------------------------------------
*/

it('la búsqueda filtra por nombre', function () {
    Organization::factory()->create(['name' => 'Corredores del Ávila']);
    Organization::factory()->create(['name' => 'Club Zulianos']);

    Livewire::test(ManageOrganizations::class)
        ->set('q', 'Ávila')
        ->assertSee('Corredores del Ávila')
        ->assertDontSee('Club Zulianos');
});

it('la búsqueda viaja en la query string (?q=)', function () {
    Organization::factory()->create(['name' => 'Alfa Runners']);
    Organization::factory()->create(['name' => 'Beta Trail']);

    Livewire::withQueryParams(['q' => 'Alfa'])
        ->test(ManageOrganizations::class)
        ->assertSee('Alfa Runners')
        ->assertDontSee('Beta Trail');
});

it('ordena por nombre ascendente por defecto', function () {
    Organization::factory()->create(['name' => 'Zeta Club']);
    Organization::factory()->create(['name' => 'Alfa Club']);

    Livewire::test(ManageOrganizations::class)
        ->assertSeeInOrder(['Alfa Club', 'Zeta Club']);
});

it('ignora el orden por columnas fuera de la lista blanca', function () {
    Organization::factory()->create(['name' => 'Zeta Club']);
    Organization::factory()->create(['name' => 'Alfa Club']);

    // ?sort=password no debe llegar al SQL: cae al orden por defecto.
    Livewire::withQueryParams(['sort' => 'password', 'direction' => 'desc'])
        ->test(ManageOrganizations::class)
        ->assertSeeInOrder(['Alfa Club', 'Zeta Club']);
});

it('ordena descendente desde la query string', function () {
    Organization::factory()->create(['name' => 'Zeta Club']);
    Organization::factory()->create(['name' => 'Alfa Club']);

    Livewire::withQueryParams(['sort' => 'name', 'direction' => 'desc'])
        ->test(ManageOrganizations::class)
        ->assertSeeInOrder(['Zeta Club', 'Alfa Club']);
});

it('ordena por fecha de creación (columna de la lista blanca)', function () {
    $antigua = Organization::factory()->create(['name' => 'Antigua']);
    $antigua->created_at = now()->subDays(5);
    $antigua->save();

    Organization::factory()->create(['name' => 'Nueva']);

    Livewire::withQueryParams(['sort' => 'created_at', 'direction' => 'desc'])
        ->test(ManageOrganizations::class)
        ->assertSeeInOrder(['Nueva', 'Antigua']);
});

it('pagina de a 10 y avanza con los links de Livewire', function () {
    seedOrganizations(25);

    $component = Livewire::test(ManageOrganizations::class);

    $component
        ->assertSee('Club 01')
        ->assertSee('Club 10')
        ->assertDontSee('Club 11')
        ->assertSeeHtml('wire:click="nextPage(\'page\')"');

    $component->call('nextPage')
        ->assertSet('paginators.page', 2)
        ->assertSee('Club 11')
        ->assertDontSee('Club 01');
});

it('mantiene la página en la query string (?page=)', function () {
    $this->actingAs(directoryAdmin());
    seedOrganizations(25);

    $this->get(route('directory.organizations', ['page' => 3]))
        ->assertOk()
        ->assertSee('Club 21')
        ->assertDontSee('Club 01');
});

/*
|--------------------------------------------------------------------------
| Estados del kit en la demo (ISSUE-111)
|--------------------------------------------------------------------------
*/

it('bloquea el doble envío y muestra esqueleto durante la consulta', function () {
    Organization::factory()->create();

    Livewire::test(ManageOrganizations::class)
        ->assertSeeHtml('wire:loading.attr="disabled"')
        ->assertSeeHtml('wire:loading.remove')
        ->assertSeeHtml('skeleton');
});

it('la acción de ver llega deshabilitada hasta que exista su pantalla', function () {
    Organization::factory()->create();

    Livewire::test(ManageOrganizations::class)
        ->assertSeeHtml('title="Próximamente"')
        ->assertSeeHtml('disabled');
});

it('muestra el estado vacío con CTA cuando no hay filas', function () {
    Livewire::test(ManageOrganizations::class)
        ->assertSee('Aún no hay organizaciones')
        ->assertSeeHtml('empty-state');
});

it('el selector de región lista las regiones del directorio', function () {
    Region::factory()->create(['name' => 'Distrito Prueba']);

    Livewire::test(ManageOrganizations::class)
        ->assertSee('Distrito Prueba');
});
