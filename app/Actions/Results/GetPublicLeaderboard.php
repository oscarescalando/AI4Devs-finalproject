<?php

declare(strict_types=1);

namespace App\Actions\Results;

use App\Models\Event;
use App\Models\Result;
use Illuminate\Support\Collection;

/**
 * Clasificación pública en vivo (readme §4 / SRS §5.3): sin login y sin
 * PII (INV-06) — solo dorsal, nombre público (menores con iniciales),
 * club, carrera, categoría, tiempo neto y posiciones calculadas en el
 * servidor. Contrato versionado con v y seq para que la pantalla pueda
 * sondear y detectar cambios.
 */
final class GetPublicLeaderboard
{
    /**
     * @return array{v: int, seq: int, status: string, rows: list<array<string, mixed>>}
     */
    public function __invoke(string $eventUlid): array
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        /** @var Collection<int, Result> $results */
        $results = Result::query()
            ->whereHas('entry', fn ($query) => $query->where('event_id', $event->id))
            ->with(['entry.person', 'entry.team.organization', 'entry.category', 'entry.race'])
            ->get()
            ->sortBy(fn (Result $r) => [$r->entry->race_id, $r->rank_general])
            ->values();

        $first = $results->first();
        $rows = [];
        foreach ($results as $result) {
            $entry = $result->entry;

            $rows[] = [
                'bib' => $entry->bib,
                'name' => $entry->person?->publicName() ?? $entry->team?->name,
                'org' => $entry->team?->organization?->name,
                'race' => $entry->race->name,
                'category' => $entry->category->name,
                'net_ms' => $result->net_ms,
                'ranks' => [
                    'general' => $result->rank_general,
                    'gender' => $result->rank_gender,
                    'category' => $result->rank_category,
                ],
                'status' => $result->status,
            ];
        }

        return [
            'v' => 1, // versión del contrato del payload
            'seq' => $event->timingMarks()->count(), // crece con cada llegada
            'status' => $first === null ? 'provisional' : $first->status,
            'rows' => $rows,
        ];
    }
}
