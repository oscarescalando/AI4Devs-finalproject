<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Actions\Events\CreateWave;
use App\Actions\Events\DeleteWave;
use App\Actions\Events\FindEvent;
use App\Actions\Events\FindWave;
use App\Actions\Events\ListWaves;
use App\Actions\Events\ReprogramWaves;
use App\Actions\Events\UpdateWave;
use App\Support\EventTime;
use App\Support\FormatClock;
use App\Support\FormatDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Editor de oleadas del evento (ISSUE-160 fase 2, TQ-026) con la
 * reprogramación en bloque de ISSUE-050: sustituye al placeholder Volt.
 * CRUD con acciones = CLASE Livewire (convención del proyecto); el evento
 * llega por su ULID del path (ADR-015) y el componente no toca la base ni
 * importa modelos: lee y escribe vía Actions de dominio (ADR-011/ADR-012).
 *
 * La hora programada viaja como pared del evento (datetime-local con
 * milisegundos); EventTime hace la puja horaria y las Actions revalidan en
 * el servidor (única fuente de verdad). «Reprogramar en bloque» es dos
 * pasos a propósito: el modal valida la hora inicial y el intervalo, la
 * confirmación muestra cuántas oleadas quedan afectadas y UNA sola
 * operación transaccional (ReprogramWaves) recoloca todas.
 */
#[Layout('components.layouts.panel')]
#[Title('Oleadas')]
final class WaveManager extends Component
{
    use AuthorizesRequests;

    public string $eventUlid = '';

    /** Zona del evento para la pared horaria de los inputs (events.timezone). */
    public string $eventTimezone = 'America/Caracas';

    /** @var array{name: string, race: string, scheduled_start_at: string} */
    public array $form = ['name' => '', 'race' => '', 'scheduled_start_at' => ''];

    public ?string $editingUlid = null;

    /** @var array{race: string, start_at: string, interval: string} */
    public array $reprogram = ['race' => '', 'start_at' => '', 'interval' => ''];

    /** Paso 2 del modal de reprogramación: resumen con el alcance real. */
    public bool $reprogramConfirm = false;

    public int $reprogramCount = 0;

    public string $reprogramFirst = '';

    public string $reprogramLast = '';

    public function mount(string $event): void
    {
        $model = app(FindEvent::class)($event);

        if ($model === null) {
            abort(404);
        }

        $this->eventUlid = $model->ulid;
        $this->eventTimezone = $model->timezone;
    }

    public function startCreate(?string $race = null): void
    {
        $this->resetForm();
        // «Añadir oleada» desde una carrera concreta la preselecciona.
        $this->form['race'] = $race ?? '';
        $this->resetValidation();
        $this->dispatch('open-wave-modal');
    }

    public function startEdit(string $ulid): void
    {
        $wave = app(FindWave::class)($this->eventUlid, $ulid);

        if ($wave === null) {
            $this->dispatch('toast', type: 'error', message: __('ui.events.wave_err_missing'));

            return;
        }

        $this->editingUlid = $wave->ulid;
        $this->form = [
            'name' => $wave->name ?? '',
            'race' => $wave->race->ulid,
            'scheduled_start_at' => $wave->scheduled_start_at !== null
                ? EventTime::inputValue($this->eventTimezone, $wave->scheduled_start_at)
                : '',
        ];

        $this->resetValidation();
        $this->dispatch('open-wave-modal');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-wave-modal');
    }

    public function save(): void
    {
        // La UI oculta el modal sin permiso de gestión; el servidor
        // autoriza igual (ISSUE-109/ISSUE-159).
        $this->authorize('events.manage');

        $creating = $this->editingUlid === null;

        $payload = [
            'race' => $this->form['race'],
            'name' => $this->form['name'],
            'scheduled_start_at' => $this->form['scheduled_start_at'],
        ];

        try {
            if ($creating) {
                app(CreateWave::class)($this->eventUlid, $payload);
            } else {
                app(UpdateWave::class)($this->eventUlid, $this->editingUlid, $payload);
            }
        } catch (ValidationException $e) {
            throw ValidationException::withMessages($this->prefixFormKeys($e->errors()));
        }

        $this->resetForm();
        $this->resetValidation();
        $this->dispatch('close-wave-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $creating ? __('ui.events.wave_toast_created') : __('ui.events.wave_toast_updated'),
        );
    }

    public function deleteWave(string $ulid): void
    {
        $this->authorize('events.manage');

        try {
            app(DeleteWave::class)($this->eventUlid, $ulid);
        } catch (ValidationException $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: __('ui.events.wave_toast_delete_failed'),
                message: (string) collect($e->errors())->flatten()->first(),
            );

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.events.wave_toast_deleted'));
    }

    /*
    |----------------------------------------------------------------------
    | Reprogramación en bloque (ISSUE-050): una carrera, hora inicial e
    | intervalo; confirmación con el alcance y UNA operación transaccional.
    |----------------------------------------------------------------------
    */

    public function startReprogram(string $raceUlid): void
    {
        $this->resetReprogram();
        $this->reprogram['race'] = $raceUlid;
        $this->resetValidation();
        $this->dispatch('open-reprogram-modal');
    }

    /**
     * Paso 1 → 2: valida la pared horaria y el intervalo, cuenta las
     * oleadas afectadas tal como están HOY y prepara el resumen que pide
     * la confirmación. La Action revalida y recalcula al aplicar: este
     * preview nunca es la fuente de verdad.
     */
    public function previewReprogram(): void
    {
        $this->authorize('events.manage');

        $this->validate([
            'reprogram.start_at' => ['required', 'date'],
            'reprogram.interval' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $count = $this->waveCount($this->reprogram['race']);

        if ($count === 0) {
            throw ValidationException::withMessages([
                'reprogram.race' => __('ui.events.wave_reprogram_empty'),
            ]);
        }

        $start = CarbonImmutable::parse($this->reprogram['start_at'], $this->eventTimezone);
        $last = $start->addMinutes(($count - 1) * (int) $this->reprogram['interval']);

        $this->reprogramCount = $count;
        $this->reprogramFirst = FormatDate::date($start).' '.FormatClock::timeOfDay($start);
        $this->reprogramLast = FormatDate::date($last).' '.FormatClock::timeOfDay($last);
        $this->reprogramConfirm = true;
    }

    /** Vuelve del resumen al formulario sin tocar nada. */
    public function editReprogram(): void
    {
        $this->reprogramConfirm = false;
    }

    public function cancelReprogram(): void
    {
        $this->resetReprogram();
        $this->resetValidation();
        $this->dispatch('close-reprogram-modal');
    }

    /**
     * Paso 2: UNA llamada a ReprogramWaves — dentro de una transacción se
     * recalculan todas las oleadas de la carrera o ninguna.
     */
    public function confirmReprogram(): void
    {
        $this->authorize('events.manage');

        try {
            $count = app(ReprogramWaves::class)(
                $this->eventUlid,
                $this->reprogram['race'],
                $this->reprogram['start_at'],
                (int) $this->reprogram['interval'],
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();

            if (array_keys($errors) === ['race']) {
                // La carrera desapareció o quedó sin oleadas: negocio, toast.
                $this->dispatch(
                    'toast',
                    type: 'error',
                    title: __('ui.events.wave_reprogram_failed'),
                    message: (string) ($errors['race'][0] ?? ''),
                );

                return;
            }

            throw ValidationException::withMessages($this->prefixReprogramKeys($errors));
        }

        $this->resetReprogram();
        $this->resetValidation();
        $this->dispatch('close-reprogram-modal');
        $this->dispatch(
            'toast',
            type: 'success',
            message: __('ui.events.wave_reprogram_toast', ['count' => $count]),
        );
    }

    public function render(): View
    {
        $list = app(ListWaves::class)($this->eventUlid);

        return view('livewire.events.wave-manager', [
            'eventName' => $list['name'],
            'races' => $list['races'],
            'waveHeaders' => [
                ['label' => __('ui.events.wave_col_wave')],
                ['label' => __('ui.events.header_scheduled'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.events.header_entries'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.events.header_actions'), 'align' => 'right'],
            ],
        ]);
    }

    /** Oleadas actuales de una carrera del evento, vía la lista del editor. */
    private function waveCount(string $raceUlid): int
    {
        $list = app(ListWaves::class)($this->eventUlid);

        foreach ($list['races'] as $race) {
            if ($race['ulid'] === $raceUlid) {
                return count($race['waves']);
            }
        }

        return 0;
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'race' => '', 'scheduled_start_at' => ''];
        $this->editingUlid = null;
    }

    private function resetReprogram(): void
    {
        $this->reprogram = ['race' => '', 'start_at' => '', 'interval' => ''];
        $this->reprogramConfirm = false;
        $this->reprogramCount = 0;
        $this->reprogramFirst = '';
        $this->reprogramLast = '';
    }

    /**
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

    /**
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function prefixReprogramKeys(array $errors): array
    {
        $prefixed = [];

        foreach ($errors as $key => $messages) {
            $prefixed['reprogram.'.$key] = $messages;
        }

        return $prefixed;
    }
}
