<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Actions\Directory\Person\ListPersons;
use App\Actions\Events\FindEvent;
use App\Actions\Events\ListCategories;
use App\Actions\Events\ListWaves;
use App\Actions\Registration\CancelEntry;
use App\Actions\Registration\CreateEntry;
use App\Actions\Registration\ListEntries;
use App\Actions\Registration\NextEntryBib;
use App\Actions\Registration\PreviewEntryCategory;
use App\Actions\Registration\ReactivateEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestor de inscripciones del evento (ISSUE-162/TQ-030): sustituye al
 * adelanto Volt entry-list. CRUD con acciones = CLASE Livewire (convención
 * del proyecto); el evento llega por su ULID del path (ADR-015) y el
 * componente no toca la base ni importa modelos: lee y escribe vía Actions
 * de dominio (ADR-011/ADR-012, custodiado por Pest Arch).
 *
 * Alta individual con picker de personas (ListPersons: documento
 * enmascarado, RNF-13), categoría AUTOMÁTICA previsualizada en vivo por
 * edad al día del evento + género (PreviewEntryCategory aplica la misma
 * regla que CreateEntry) y dorsal sugerido siguiente libre (editable). La
 * baja es lógica (status cancelled) y se revierte con la reactivación —
 * nunca se borra una fila.
 *
 * Autorización: la ruta exige `entries.view` (ISSUE-159) y cada acción que
 * muta comprueba `entries.manage` — la UI oculta el botón, el servidor
 * siempre autoriza (ADR-011).
 */
#[Layout('components.layouts.panel')]
#[Title('Inscripciones')]
final class EntryManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Estados del filtro: los del esquema 000003 (default registered). */
    private const STATUSES = ['registered', 'cancelled'];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'race', except: '')]
    public string $race = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $eventUlid = '';

    /** Búsqueda del picker de personas dentro del modal. */
    public string $personQuery = '';

    /** @var array{person_ulid: string, race_ulid: string, wave_ulid: string, bib: string} */
    public array $form = ['person_ulid' => '', 'race_ulid' => '', 'wave_ulid' => '', 'bib' => ''];

    /**
     * Resumen de la persona elegida y de la categoría que se asignará.
     *
     * @var array{name: string, document: string, age: int, gender: string, category: string|null}|null
     */
    public ?array $selected = null;

    public function mount(string $event): void
    {
        $model = app(FindEvent::class)($event);

        if ($model === null) {
            abort(404);
        }

        $this->eventUlid = $model->ulid;
    }

    /**
     * Hook genérico de actualización: los filtros devuelven al primer bloque
     * de resultados y cambiar la carrera del alta reencierra la oleada (la
     * oleada debe pertenecer a la carrera elegida).
     */
    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'race', 'category', 'status'], true)) {
            $this->resetPage();

            return;
        }

        if ($name === 'form.race_ulid') {
            $this->form['wave_ulid'] = '';
        }
    }

    /** Suelta los cuatro filtros (búsqueda, carrera, categoría y estado). */
    public function clearFilters(): void
    {
        $this->q = '';
        $this->race = '';
        $this->category = '';
        $this->status = '';
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->resetValidation();
        // Dorsal sugerido: siguiente libre del evento (editable en el modal).
        $this->form['bib'] = (string) app(NextEntryBib::class)($this->eventUlid);
        $this->dispatch('open-entry-modal');
    }

    /**
     * Elige una persona del picker: fija su ULID y pinta la previsualización
     * de la categoría (edad al día del evento + género) ANTES de guardar.
     */
    public function selectPerson(string $ulid): void
    {
        $preview = app(PreviewEntryCategory::class)($this->eventUlid, $ulid);

        if ($preview === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.directory.persons.toast_missing'));

            return;
        }

        $this->form['person_ulid'] = $ulid;
        $this->selected = $preview;
        $this->resetValidation('form.person_ulid');
    }

    /** Suelta la persona elegida para buscar otra en el picker. */
    public function changePerson(): void
    {
        $this->form['person_ulid'] = '';
        $this->selected = null;
        $this->personQuery = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-entry-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de gestión; el servidor
        // autoriza igual (ISSUE-109/ISSUE-159).
        $this->authorize('entries.manage');

        $bib = trim($this->form['bib']);

        try {
            $entry = app(CreateEntry::class)($this->eventUlid, [
                'person_ulid' => $this->form['person_ulid'],
                'race_ulid' => $this->form['race_ulid'],
                'wave_ulid' => $this->form['wave_ulid'],
                'bib' => $bib === '' ? null : (int) $bib,
            ]);
        } catch (ValidationException $e) {
            // El error del servidor viaja al campo del formulario: es la
            // única fuente de verdad.
            throw ValidationException::withMessages($this->prefixFormKeys($e->errors()));
        }

        $name = $entry->person?->publicName() ?? $entry->team?->name;

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-entry-modal');
        $this->dispatch('toast', type: 'success', message: __('ui.events.entry_toast_created', [
            'name' => $name,
            'bib' => $entry->bib,
        ]));
    }

    public function cancelEntry(string $ulid): void
    {
        $this->authorize('entries.manage');

        try {
            $entry = app(CancelEntry::class)($this->eventUlid, $ulid);
        } catch (ValidationException $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.events.entry_toast_failed'),
                message: (string) collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.events.entry_toast_cancelled', [
            'name' => $entry->person?->publicName() ?? $entry->team?->name,
            'bib' => $entry->bib,
        ]));
    }

    public function reactivateEntry(string $ulid): void
    {
        $this->authorize('entries.manage');

        try {
            $entry = app(ReactivateEntry::class)($this->eventUlid, $ulid);
        } catch (ValidationException $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.events.entry_toast_failed'),
                message: (string) collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.events.entry_toast_reactivated', [
            'name' => $entry->person?->publicName() ?? $entry->team?->name,
            'bib' => $entry->bib,
        ]));
    }

    public function render(): View
    {
        $waves = app(ListWaves::class)($this->eventUlid);
        $categories = app(ListCategories::class)($this->eventUlid);

        return view('livewire.events.entry-manager', [
            'eventName' => $waves['name'],
            'entries' => app(ListEntries::class)($this->eventUlid, [
                'q' => $this->q,
                'race' => $this->race,
                'category' => $this->category,
                'status' => $this->status,
            ]),
            'races' => $waves['races'],
            'categories' => $categories['rows'],
            'personResults' => trim($this->personQuery) !== ''
                ? app(ListPersons::class)(['q' => $this->personQuery])
                : null,
            'statuses' => self::STATUSES,
            'headers' => [
                ['label' => __('ui.events.header_bib'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.events.header_athlete')],
                ['label' => __('ui.events.entry_header_category')],
                ['label' => __('ui.events.entry_header_race')],
                ['label' => __('ui.events.header_status')],
                ['label' => __('ui.events.header_actions'), 'align' => 'right'],
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->form = ['person_ulid' => '', 'race_ulid' => '', 'wave_ulid' => '', 'bib' => ''];
        $this->personQuery = '';
        $this->selected = null;
    }

    /**
     * Mueve las claves de error de la Action (bib, person_ulid…) al camino
     * del formulario Livewire (form.*) para que el kit las pinte bajo el
     * campo.
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
