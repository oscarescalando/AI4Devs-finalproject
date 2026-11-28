<?php

declare(strict_types=1);

namespace App\Actions\Start;

use App\Models\Event;
use App\Models\Wave;
use App\Support\FormatClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * ISSUE-133 (TQ-013, versión mínima F1) — Marca la salida real de una
 * oleada: guarda `actual_start_at` con el AHORA del servidor (jamás la
 * programada, que queda intacta como columna distinta — RF-15) y solo
 * sobre la PRIMERA oleada sin salida en el orden programado: si alguna
 * anterior sigue pendiente, rechaza con error de negocio — las salidas
 * dadas forman un prefijo del tablero.
 *
 * La confirmación y el toast los resuelve la pantalla; el broadcast
 * `wave.started` afterCommit llega con EP-26 (diferido documentado).
 */
final readonly class MarkWaveStart
{
    /**
     * @return array{ulid: string, name: string, actual: string}
     */
    public function __invoke(Event $event, string $waveUlid): array
    {
        $ordered = $this->orderedWaves($event);

        $position = $ordered->search(fn (Wave $candidate): bool => $candidate->ulid === $waveUlid);

        if ($position === false) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.start_err_missing'),
            ]);
        }

        /** @var Wave $wave */
        $wave = $ordered->get($position);

        if ($wave->actual_start_at !== null) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.start_err_already', ['name' => $this->label($wave)]),
            ]);
        }

        /** @var Wave|null $earlierPending */
        $earlierPending = $ordered->take((int) $position)
            ->first(fn (Wave $candidate): bool => $candidate->actual_start_at === null);

        if ($earlierPending !== null) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.start_err_not_first', ['first' => $this->label($earlierPending)]),
            ]);
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        $wave->fill(['actual_start_at' => $now])->save();

        return [
            'ulid' => $wave->ulid,
            'name' => $this->label($wave),
            'actual' => FormatClock::timeOfDay($now, $event->timezone),
        ];
    }

    /**
     * Oleadas del evento en el orden del tablero (ISSUE-132).
     *
     * @return Collection<int, Wave>
     */
    private function orderedWaves(Event $event): Collection
    {
        return Wave::query()
            ->whereRelation('race', 'event_id', $event->id)
            ->with('race')
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get();
    }

    private function label(Wave $wave): string
    {
        return $wave->name ?? __('ui.events.start_wave_fallback');
    }
}
