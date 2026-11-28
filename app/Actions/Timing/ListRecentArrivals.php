<?php

declare(strict_types=1);

namespace App\Actions\Timing;

use App\Models\Event;
use App\Models\TimingMark;
use App\Support\FormatClock;

/**
 * ISSUE-130 (TQ-012) — Llegadas recientes para la pantalla de captura: las
 * últimas 15 marcas del evento, la más nueva primero, con el contrato
 * público de la UI (INV-06: dorsal, nombre público y club, nada más) y la
 * hora del día en la zona del evento. La primera fila de la lista es la
 * última llegada: la pantalla la resalta con accent (spec/09-ui-estilo §3).
 */
final readonly class ListRecentArrivals
{
    private const LIMIT = 15;

    /**
     * @return list<array{bib: int|null, name: string|null, club: string|null, time: string, status: string}>
     */
    public function __invoke(Event $event): array
    {
        $marks = TimingMark::query()
            ->where('event_id', $event->id)
            ->with(['entry.person', 'entry.team.organization'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = [];
        foreach ($marks as $mark) {
            $rows[] = [
                'bib' => $mark->entry?->bib,
                'name' => $mark->entry?->person?->publicName() ?? $mark->entry?->team?->name,
                'club' => $mark->entry?->team?->organization?->name,
                'time' => FormatClock::timeOfDay($mark->captured_at, $event->timezone),
                'status' => $mark->entry_id !== null ? 'assigned' : 'orphan',
            ];
        }

        return $rows;
    }
}
