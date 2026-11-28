<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Support\FormatClock;
use App\Support\FormatDate;
use Carbon\CarbonInterface;

/**
 * Oleadas del evento agrupadas por carrera para el editor del panel
 * (TQ-026/ISSUE-160): una fila de carrera con sus oleadas en el orden del
 * tablero de salidas (hora programada, empate por id — las sin hora quedan
 * primero en MySQL ASC). La hora programada se muestra en la pared del
 * evento como dd/mm/yyyy H:i:s.v (INV-10: milisegundos y zona del evento)
 * y llega formateada para que la vista no convierta nada.
 */
final readonly class ListWaves
{
    /**
     * @return array{name: string, races: list<array{
     *     ulid: string,
     *     name: string,
     *     waves: list<array{
     *         ulid: string,
     *         name: string,
     *         scheduled: string|null,
     *         entries_total: int,
     *     }>,
     * }>}
     */
    public function __invoke(string $eventUlid): array
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $races = $event->races()
            ->with(['waves' => function ($query): void {
                $query->withCount('entries')->orderBy('scheduled_start_at')->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($races as $race) {
            $waves = [];
            foreach ($race->waves as $wave) {
                $waves[] = [
                    'ulid' => $wave->ulid,
                    'name' => $wave->name ?? __('ui.events.start_wave_fallback'),
                    'scheduled' => $wave->scheduled_start_at !== null
                        ? $this->scheduled($wave->scheduled_start_at, $event->timezone)
                        : null,
                    'entries_total' => (int) $wave->entries_count,
                ];
            }

            $rows[] = ['ulid' => $race->ulid, 'name' => $race->name, 'waves' => $waves];
        }

        return ['name' => $event->name, 'races' => $rows];
    }

    /**
     * dd/mm/yyyy H:i:s.v en la zona del evento (spec/09-ui-estilo.md §8).
     */
    private function scheduled(CarbonInterface $at, string $timezone): string
    {
        $local = $at->setTimezone($timezone);

        return FormatDate::date($local).' '.FormatClock::timeOfDay($local);
    }
}
