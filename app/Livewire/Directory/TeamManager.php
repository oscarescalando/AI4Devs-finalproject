<?php

declare(strict_types=1);

namespace App\Livewire\Directory;

use App\Actions\Directory\Team\CreateTeam;
use App\Actions\Directory\Team\DeleteTeam;
use App\Actions\Directory\Team\FindTeam;
use App\Actions\Directory\Team\ListOrganizationOptions;
use App\Actions\Directory\Team\ListTeams;
use App\Actions\Directory\Team\UpdateTeam;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Directorio de equipos (ISSUE-161, TQ-028): sustituye al placeholder Volt.
 * CRUD con acciones = CLASE Livewire (convención del proyecto); el
 * componente no toca la base ni importa modelos: lee y escribe vía Actions
 * de dominio (ADR-011/ADR-012, custodiado por Pest Arch).
 *
 * La organización viaja SIEMPRE por su ULID público (ADR-015): en el filtro
 * ?org= de la query string y en el selector del modal. El borrado lo bloquea
 * el Action cuando el equipo tiene inscripciones (FK restrict).
 *
 * Autorización: la ruta exige `directory.view` (ISSUE-159) y cada acción
 * que muta comprueba la habilidad `directory.write` (convención en
 * AppServiceProvider) — la UI oculta, el servidor decide.
 */
#[Layout('components.layouts.panel')]
#[Title('Equipos')]
final class TeamManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'org', except: '')]
    public string $org = '';

    /** @var array{name: string, organization_ulid: string} */
    public array $form = ['name' => '', 'organization_ulid' => ''];

    public ?string $editingUlid = null;

    /** @var list<array{ulid: string, name: string}> */
    public array $organizations = [];

    public function mount(): void
    {
        $this->organizations = app(ListOrganizationOptions::class)();
    }

    public function updatedQ(): void
    {
        // Cambiar la búsqueda devuelve al primer bloque de resultados.
        $this->resetPage();
    }

    public function updatedOrg(): void
    {
        // Cambiar la organización filtrada también repagina.
        $this->resetPage();
    }

    /** Deja la tabla sin búsqueda ni organización filtrada. */
    public function clearFilters(): void
    {
        $this->q = '';
        $this->org = '';
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('open-team-modal');
    }

    public function startEdit(string $ulid): void
    {
        $team = app(FindTeam::class)($ulid);

        if ($team === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.directory.teams.toast_missing'));

            return;
        }

        $this->editingUlid = $team->ulid;
        $this->form = [
            'name' => $team->name,
            'organization_ulid' => $team->organization->ulid ?? '',
        ];

        $this->resetValidation();
        $this->dispatch('open-team-modal');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-team-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de escritura; el servidor
        // autoriza igual (ISSUE-109).
        $this->authorize('directory.write');

        $creating = $this->editingUlid === null;

        try {
            if ($creating) {
                app(CreateTeam::class)($this->form);
            } else {
                app(UpdateTeam::class)($this->editingUlid, $this->form);
            }
        } catch (ValidationException $e) {
            // El error del servidor viaja al campo del formulario: es la
            // única fuente de verdad.
            throw ValidationException::withMessages($this->prefixFormKeys($e->errors()));
        }

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-team-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $creating ? __('ui.directory.teams.toast_created') : __('ui.directory.teams.toast_updated'),
        );
    }

    public function deleteTeam(string $ulid): void
    {
        $this->authorize('directory.write');

        try {
            app(DeleteTeam::class)($ulid);
        } catch (ValidationException $e) {
            // Borrado bloqueado por inscripciones: error de negocio en
            // toast, la fila sigue en la tabla.
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.directory.teams.toast_delete_failed'),
                message: (string) collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.directory.teams.toast_deleted'));
    }

    public function render(): View
    {
        return view('livewire.directory.team-manager', [
            'teams' => app(ListTeams::class)(['q' => $this->q, 'org' => $this->org]),
            'headers' => [
                ['label' => __('ui.directory.teams.header_name')],
                ['label' => __('ui.directory.teams.header_organization')],
                ['label' => __('ui.directory.teams.header_actions'), 'align' => 'right'],
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'organization_ulid' => ''];
        $this->editingUlid = null;
    }

    /**
     * Mueve las claves de error de la Action (name, organization_ulid) al
     * camino del formulario Livewire (form.*) para que el kit las pinte
     * bajo el campo.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function prefixFormKeys(array $errors): array
    {
        $prefixed = [];

        foreach ($errors as $key => $messages) {
            $prefixed['form.'.$key] = $messages;
        }

        return $prefixed;
    }
}
