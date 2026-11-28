<?php

declare(strict_types=1);

namespace App\Actions\Timing;

use App\Models\Event;
use App\Models\TimingMark;
use App\Support\FormatClock;

/**
 * ISSUE-130 (TQ-012) — Huérfanas visibles: las últimas 10 marcas sin
 * inscripción del evento para que el juez las resuelva (ISSUE-135 llega con
 * las correcciones). timing_marks no persiste el dorsal tecleado de una
 * huérfana (la asignación por dorsal vive en entries), así que lo que hay
 * para operar es la hora oficial y el origen de la marca.
 */
final readonly class ListOrphanMarks
{
    private const LIMIT = 10;

    /**
     * @return list<array{time: string, source: string}>
     */
    public function __invoke(Event $event): array
    {
        $marks = TimingMark::query()
            ->where('event_id', $event->id)
            ->whereNull('entry_id')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = [];
        foreach ($marks as $mark) {
            $rows[] = [
                'time' => FormatClock::timeOfDay($mark->captured_at, $event->timezone),
                'source' => $mark->source,
            ];
        }

        return $rows;
    }
}
