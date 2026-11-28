<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\FindEvent;
use App\Actions\Events\ListEvents;
use App\Actions\Events\UpdateEvent;
use App\Support\FormatDate;
use App\Support\Ui\EventStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de eventos y carreras (ISSUE-160 fase 1, TQ-025): sustituye al
 * placeholder Volt del listado. Decisión de estructura: al ganar acciones de
 * escritura pasa a CLASE Livewire (convención «CRUD con acciones = clase
 * Livewire» del proyecto) y la ruta events.index apunta aquí; el componente
 * no toca la base — lee y escribe vía Actions de dominio (ADR-011/ADR-012).
 *
 * Sin botón eliminar (DEFER documentado en TQ-025): un evento con
 * inscripciones rompe las FK restrict — los eventos se cierran cambiando su
 * estado, no se borran.
 *
 * Autorización: la ruta exige `events.view` para leer; las acciones que
 * mutan comprueban el permiso `events.manage` del catálogo (ISSUE-159) —
 * la UI oculta el botón, el servidor siempre autoriza (ADR-011).
 */
#[Layout('components.layouts.panel')]
final class EventList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Columnas ordenables: lista blanca — cualquier otra se ignora. */
    private const SORTABLE = ['name', 'date'];

    /** Estado inicial por defecto del formulario de carrera nueva. */
    private const NEW_RACE = ['id' => null, 'name' => ''];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(except: 'date')]
    public string $sort = 'date';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    /** @var array{name: string, slug: string, date: string, status: string} */
    public array $form = ['name' => '', 'slug' => '', 'date' => '', 'status' => ''];

    /** @var list<array{id: string|null, name: string}> */
    public array $races = [];

    public ?string $editingUlid = null;

    public function mount(): void
    {
        // El hub enlaza al editor con ?edit={ulid} (TQ-025): si el evento
        // existe, abre el modal precargado en la primera carga.
        $edit = request()->query('edit');

        if (is_string($edit) && $edit !== '') {
            $this->startEdit($edit);
        }
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
        $this->dispatch('open-event-modal');
    }

    public function startEdit(string $ulid): void
    {
        $event = app(FindEvent::class)($ulid);

        if ($event === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.events.toast_missing'));

            return;
        }

        $this->editingUlid = $event->ulid;
        $this->form = [
            'name' => $event->name,
            'slug' => $event->slug,
            'date' => FormatDate::iso($event->date),
            'status' => $event->status,
        ];

        $races = [];
        foreach ($event->races as $race) {
            $races[] = ['id' => (string) $race->id, 'name' => $race->name];
        }
        $this->races = $races;

        $this->resetValidation();
        $this->dispatch('open-event-modal');
    }

    public function addRace(): void
    {
        $this->races[] = self::NEW_RACE;
        // Los índices de los errores bajo campo ya no apuntan a la fila
        // correcta tras añadir/quitar: se limpian.
        $this->resetValidation();
    }

    public function removeRace(int $index): void
    {
        $kept = [];
        foreach ($this->races as $i => $race) {
            if ($i !== $index) {
                $kept[] = $race;
            }
        }
        $this->races = $kept;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-event-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de gestión; el servidor
        // autoriza igual (ISSUE-109/ISSUE-159).
        $this->authorize('events.manage');

        $creating = $this->editingUlid === null;

        // Slug autogenerado desde el nombre cuando llega vacío (editable:
        // si el usuario escribió uno, se respeta).
        if ($creating && trim($this->form['slug']) === '') {
            $this->form['slug'] = Str::slug(trim($this->form['name']));
        }

        $payload = [
            'name' => $this->form['name'],
            'slug' => $this->form['slug'],
            'date' => $this->form['date'],
            'races' => array_map(
                static fn (array $race): array => ['id' => $race['id'], 'name' => $race['name']],
                $this->races,
            ),
        ];

        // El estado solo se envía al editar: en la creación el evento nace
        // con el estado inicial del esquema (default 'live').
        if (! $creating) {
            $payload['status'] = $this->form['status'];
        }

        try {
            if ($creating) {
                app(CreateEvent::class)($payload);
            } else {
                app(UpdateEvent::class)($this->editingUlid, $payload);
            }
        } catch (ValidationException $e) {
            $errors = $e->errors();

            // Error de negocio (carrera retirada con inscripciones/oleadas):
            // la fila ya no está en el formulario, no hay campo bajo el que
            // pintarlo — se avisa por toast y el modal queda abierto sin
            // tocar la base (la transacción se revirtió).
            if (array_keys($errors) === ['races']) {
                $this->dispatch(
                    'toast',
                    type: 'error',
                    title: __('ui.events.toast_save_failed'),
                    message: (string) ($errors['races'][0] ?? ''),
                );

                return;
            }

            // El error del servidor viaja al campo del formulario: es la
            // única fuente de verdad.
            throw ValidationException::withMessages($this->mapErrorKeys($errors));
        }

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-event-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $creating ? __('ui.events.toast_created') : __('ui.events.toast_updated'),
        );
    }

    public function render(): View
    {
        [$sort, $direction] = $this->effectiveOrder();

        return view('livewire.events.event-list', [
            'events' => app(ListEvents::class)([
                'q' => $this->q,
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'headers' => $this->tableHeaders($sort, $direction),
            'statusOptions' => EventStatus::STATUSES,
        ]);
    }

    /**
     * Cabeceras para x-ui.table: columnas ordenables con enlace (estado en
     * query string) e icono de dirección solo en la columna activa.
     *
     * @return list<array<string, mixed>>
     */
    private function tableHeaders(string $sort, string $direction): array
    {
        return [
            [
                'label' => __('ui.events.header_event'),
                'href' => $this->sortHref('name', $sort, $direction),
                'icon' => $sort === 'name' ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : null,
            ],
            [
                'label' => __('ui.events.header_date'),
                'align' => 'right',
                'class' => 'mono',
                'href' => $this->sortHref('date', $sort, $direction),
                'icon' => $sort === 'date' ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : null,
            ],
            ['label' => __('ui.events.header_races'), 'align' => 'right', 'class' => 'mono'],
            ['label' => __('ui.events.header_entries'), 'align' => 'right', 'class' => 'mono'],
            ['label' => __('ui.events.header_status')],
            ['label' => __('ui.events.header_actions'), 'align' => 'right'],
        ];
    }

    private function sortHref(string $column, string $sort, string $direction): string
    {
        return route('events.index', array_filter([
            'q' => trim($this->q) !== '' ? $this->q : null,
            'sort' => $column,
            'direction' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc',
        ]));
    }

    /**
     * Orden efectivo (columna, dirección): fuera de la lista blanca se
     * IGNORA el orden completo y cae al default fecha descendente.
     *
     * @return array{0: string, 1: string}
     */
    private function effectiveOrder(): array
    {
        if (in_array($this->sort, self::SORTABLE, true)) {
            return [$this->sort, strtolower($this->direction) === 'desc' ? 'desc' : 'asc'];
        }

        return ['date', 'desc'];
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'slug' => '', 'date' => '', 'status' => ''];
        $this->races = [];
        $this->editingUlid = null;
    }

    /**
     * Mueve las claves de error de la Action al camino de las propiedades
     * Livewire para que el kit las pinte bajo el campo: los datos del evento
     * van en `form.*` y las filas del repeater de carreras quedan en su
     * propio índice `races.N.name`.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function mapErrorKeys(array $errors): array
    {
        $mapped = [];

        foreach ($errors as $key => $messages) {
            if (str_starts_with($key, 'races')) {
                $mapped[$key] = $messages;
            } else {
                $mapped['form.'.$key] = $messages;
            }
        }

        return $mapped;
    }
}
