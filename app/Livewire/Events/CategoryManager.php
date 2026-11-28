<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Actions\Events\CreateCategory;
use App\Actions\Events\DeleteCategory;
use App\Actions\Events\FindCategory;
use App\Actions\Events\FindEvent;
use App\Actions\Events\ListCategories;
use App\Actions\Events\UpdateCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Editor de categorías del evento (ISSUE-160 fase 2, TQ-026): sustituye al
 * placeholder Volt. CRUD con acciones = CLASE Livewire (convención del
 * proyecto); el evento llega por su ULID del path (ADR-015) y el componente
 * no toca la base ni importa modelos: lee y escribe vía Actions de dominio
 * (ADR-011/ADR-012, custodiado por Pest Arch).
 *
 * Validación de coherencia (columnas reales del esquema 000002): nombre
 * único por evento, min/max 1–120 con max >= min y sin solapamiento de
 * rangos dentro del mismo género-tokens del nombre (CategoryRange). El
 * borrado lo bloquea el servidor cuando la categoría tiene inscripciones
 * (FK restrict) con error de negocio en toast.
 *
 * Autorización: la ruta exige `events.manage` (ISSUE-159) y cada acción
 * que muta vuelve a autorizarla — la UI oculta, el servidor decide.
 */
#[Layout('components.layouts.panel')]
#[Title('Categorías')]
final class CategoryManager extends Component
{
    use AuthorizesRequests;

    public string $eventUlid = '';

    /** @var array{name: string, for_teams: bool, min_age: int|string|null, max_age: int|string|null} */
    public array $form = ['name' => '', 'for_teams' => false, 'min_age' => null, 'max_age' => null];

    public ?string $editingUlid = null;

    public function mount(string $event): void
    {
        $model = app(FindEvent::class)($event);

        if ($model === null) {
            abort(404);
        }

        $this->eventUlid = $model->ulid;
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('open-category-modal');
    }

    public function startEdit(string $ulid): void
    {
        $category = app(FindCategory::class)($this->eventUlid, $ulid);

        if ($category === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.events.cat_err_missing'));

            return;
        }

        $this->editingUlid = $category->ulid;
        $this->form = [
            'name' => $category->name,
            'for_teams' => $category->for_teams,
            'min_age' => $category->min_age,
            'max_age' => $category->max_age,
        ];

        $this->resetValidation();
        $this->dispatch('open-category-modal');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-category-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de gestión; el servidor
        // autoriza igual (ISSUE-109/ISSUE-159).
        $this->authorize('events.manage');

        $creating = $this->editingUlid === null;

        $payload = [
            'name' => $this->form['name'],
            'for_teams' => $this->form['for_teams'],
            'min_age' => $this->blankable($this->form['min_age']),
            'max_age' => $this->blankable($this->form['max_age']),
        ];

        try {
            if ($creating) {
                app(CreateCategory::class)($this->eventUlid, $payload);
            } else {
                app(UpdateCategory::class)($this->eventUlid, $this->editingUlid, $payload);
            }
        } catch (ValidationException $e) {
            // El error del servidor viaja al campo del formulario: es la
            // única fuente de verdad.
            throw ValidationException::withMessages($this->prefixFormKeys($e->errors()));
        }

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-category-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $creating ? __('ui.events.cat_toast_created') : __('ui.events.cat_toast_updated'),
        );
    }

    public function deleteCategory(string $ulid): void
    {
        $this->authorize('events.manage');

        try {
            app(DeleteCategory::class)($this->eventUlid, $ulid);
        } catch (ValidationException $e) {
            // Borrado bloqueado por inscripciones: error de negocio en
            // toast, la fila sigue en la tabla.
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.events.cat_toast_delete_failed'),
                message: (string) collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.events.cat_toast_deleted'));
    }

    public function render(): View
    {
        $list = app(ListCategories::class)($this->eventUlid);

        return view('livewire.events.category-manager', [
            'eventName' => $list['name'],
            'categories' => $list['rows'],
            'headers' => [
                ['label' => __('ui.events.name')],
                ['label' => __('ui.events.cat_header_type')],
                ['label' => __('ui.events.cat_header_range'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.events.header_entries'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.events.header_actions'), 'align' => 'right'],
            ],
        ]);
    }

    /** Un input numérico vacío llega como null o '': ambos significan «sin tope». */
    private function blankable(int|string|null $value): int|string|null
    {
        return $value === '' ? null : $value;
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'for_teams' => false, 'min_age' => null, 'max_age' => null];
        $this->editingUlid = null;
    }

    /**
     * Mueve las claves de error de la Action (name, max_age…) al camino del
     * formulario Livewire (form.*) para que el kit las pinte bajo el campo.
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
