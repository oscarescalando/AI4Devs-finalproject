<?php

declare(strict_types=1);

namespace App\Livewire\Directory;

use App\Actions\Directory\CreateOrganization;
use App\Actions\Directory\DeleteOrganization;
use App\Actions\Directory\FindOrganization;
use App\Actions\Directory\ListOrganizations;
use App\Actions\Directory\ListRegions;
use App\Actions\Directory\UpdateOrganization;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD demo Organizaciones (ISSUE-110, TQ-003): construida SOLO con el kit
 * x-ui.* y sin CSS/JS ad-hoc. El componente no toca la base: lee y escribe
 * vía Actions de dominio (ADR-011/ADR-012). El estado de la tabla (búsqueda,
 * orden y página) vive en la query string para compartir URL (ISSUE-115).
 *
 * Autorización (ISSUE-109, TQ-008): la ruta exige `directory.view` para
 * leer; las acciones que mutan comprueban la habilidad `directory.write`
 * (convención documentada en AppServiceProvider) — la UI oculta el botón,
 * el servidor siempre autoriza.
 */
#[Layout('components.layouts.panel')]
final class ManageOrganizations extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Columnas ordenables: lista blanca — cualquier otra se ignora. */
    private const SORTABLE = ['name', 'created_at'];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    /** @var array{name: string, region_id: string|null} */
    public array $form = ['name' => '', 'region_id' => null];

    public ?string $editingUlid = null;

    /** @var list<array{id: int, name: string}> */
    public array $regions = [];

    public function mount(): void
    {
        $this->regions = app(ListRegions::class)();
    }

    public function updatedQ(): void
    {
        // Cambiar la búsqueda devuelve al primer bloque de resultados.
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('open-org-modal');
    }

    public function startEdit(string $ulid): void
    {
        $organization = app(FindOrganization::class)($ulid);

        if ($organization === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.directory.organizations.toast_missing'));

            return;
        }

        $this->editingUlid = $organization->ulid;
        $this->form = [
            'name' => $organization->name,
            'region_id' => (string) $organization->region_id,
        ];
        $this->resetValidation();
        $this->dispatch('open-org-modal');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-org-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de escritura; el servidor
        // autoriza igual (ISSUE-109).
        $this->authorize('directory.write');

        $creating = $this->editingUlid === null;

        try {
            if ($creating) {
                app(CreateOrganization::class)($this->form);
            } else {
                app(UpdateOrganization::class)($this->editingUlid, $this->form);
            }
        } catch (ValidationException $e) {
            // El error del servidor viaja al campo del formulario: es la
            // única fuente de verdad (ISSUE-110).
            throw ValidationException::withMessages($this->prefixFormKeys($e->errors()));
        }

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-org-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $creating ? __('ui.directory.organizations.toast_created') : __('ui.directory.organizations.toast_updated'),
        );
    }

    public function deleteOrganization(string $ulid): void
    {
        $this->authorize('directory.write');

        try {
            app(DeleteOrganization::class)($ulid);
        } catch (ValidationException $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.directory.organizations.toast_delete_failed'),
                message: collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.directory.organizations.toast_deleted'));
    }

    public function render(): View
    {
        [$sort, $direction] = $this->effectiveOrder();

        return view('livewire.directory.manage-organizations', [
            'organizations' => app(ListOrganizations::class)([
                'q' => $this->q,
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'headers' => $this->tableHeaders($sort, $direction),
        ]);
    }

    /**
     * Cabeceras para x-ui.table: columnas ordenables con enlace (estado en
     * query string) e icono de dirección solo en la columna activa. Los
     * labels llegan de lang/es/ui.php (TQ-007).
     *
     * @return list<array<string, mixed>>
     */
    private function tableHeaders(string $sort, string $direction): array
    {
        return [
            [
                'label' => __('ui.directory.organizations.name'),
                'href' => $this->sortHref('name', $sort, $direction),
                'icon' => $sort === 'name' ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : null,
            ],
            ['label' => __('ui.directory.organizations.region')],
            [
                'label' => __('ui.directory.organizations.header_created'),
                'align' => 'right',
                'class' => 'mono',
                'href' => $this->sortHref('created_at', $sort, $direction),
                'icon' => $sort === 'created_at' ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : null,
            ],
            ['label' => __('ui.directory.organizations.header_actions'), 'align' => 'right'],
        ];
    }

    private function sortHref(string $column, string $sort, string $direction): string
    {
        return route('directory.organizations', array_filter([
            'q' => trim($this->q) !== '' ? $this->q : null,
            'sort' => $column,
            'direction' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc',
        ]));
    }

    /**
     * Orden efectivo (columna, dirección): fuera de la lista blanca se
     * IGNORA el orden completo y cae al default name asc (ISSUE-110).
     *
     * @return array{0: string, 1: string}
     */
    private function effectiveOrder(): array
    {
        if (in_array($this->sort, self::SORTABLE, true)) {
            return [$this->sort, strtolower($this->direction) === 'desc' ? 'desc' : 'asc'];
        }

        return ['name', 'asc'];
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'region_id' => null];
        $this->editingUlid = null;
    }

    /**
     * Mueve las claves de error de la Action (name, region_id) al camino del
     * formulario Livewire (form.name, form.region_id) para que el kit las
     * pinte bajo el campo.
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
