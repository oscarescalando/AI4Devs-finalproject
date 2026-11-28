<?php

declare(strict_types=1);

namespace App\Actions\Start;

use App\Models\Event;
use App\Models\Wave;
use App\Support\FormatClock;
use Illuminate\Support\Collection;

/**
 * ISSUE-132 (TQ-013, versión mínima F1) — Oleadas del tablero de salidas:
 * ordenadas por hora programada (empate por id), con la real, el estado
 * derivado (Pendiente/Iniciada) y los inscritos como TOTAL de la oleada —
 * withCount sobre todos sus entries, jamás una página (RF-16). Las horas
 * llegan formateadas en la zona del evento (spec/09-ui-estilo.md §8).
 *
 * `is_next` marca la PRIMERA oleada sin salida real: la única que la
 * pantalla habilita para «Marcar salida» (RF-15, ISSUE-133).
 */
final readonly class ListWaveBoard
{
    /**
     * @return list<array{
     *     ulid: string,
     *     name: string,
     *     race: string,
     *     scheduled: string|null,
     *     actual: string|null,
     *     differs: bool,
     *     entries_total: int,
     *     status: string,
     *     is_next: bool
     * }>
     */
    public function __invoke(Event $event): array
    {
        /** @var Collection<int, Wave> $waves */
        $waves = Wave::query()
            ->whereRelation('race', 'event_id', $event->id)
            ->with('race')
            ->withCount('entries')
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $seenPending = false;

        foreach ($waves as $wave) {
            $hasActual = $wave->actual_start_at !== null;
            $differs = $hasActual
                && $wave->scheduled_start_at !== null
                && $wave->actual_start_at->getTimestampMs() !== $wave->scheduled_start_at->getTimestampMs();

            $rows[] = [
                'ulid' => $wave->ulid,
                'name' => $wave->name ?? __('ui.events.start_wave_fallback'),
                'race' => $wave->race->name,
                'scheduled' => $wave->scheduled_start_at !== null
                    ? FormatClock::timeOfDay($wave->scheduled_start_at, $event->timezone)
                    : null,
                'actual' => $hasActual
                    ? FormatClock::timeOfDay($wave->actual_start_at, $event->timezone)
                    : null,
                'differs' => $differs,
                'entries_total' => (int) $wave->entries_count,
                'status' => $hasActual ? 'started' : 'pending',
                'is_next' => ! $hasActual && ! $seenPending,
            ];

            $seenPending = $seenPending || ! $hasActual;
        }

        return $rows;
    }
}
