<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Event;

/**
 * Descarga del roster de inscritos para la estación (readme §4 / SRS §5.3):
 * lo consulta la estación al abrir con conexión para cruzar dorsales en
 * local y avisar de un dorsal desconocido sin red. Solo campos públicos
 * por diseño (INV-06): nombre público con iniciales para menores.
 */
final class GetEventRoster
{
    /**
     * @return array{v: int, rows: list<array<string, mixed>>}
     */
    public function __invoke(string $eventUlid): array
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $entries = $event->entries()
            ->with(['person', 'team.organization', 'category', 'race', 'wave'])
            ->orderBy('bib')
            ->get();

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'bib' => $entry->bib,
                'name' => $entry->person?->publicName() ?? $entry->team?->name,
                'org' => $entry->team?->organization?->name,
                'race' => $entry->race->name,
                'category' => $entry->category->name,
                'wave' => $entry->wave->ulid,
                'status' => $entry->status,
            ];
        }

        // Versión del roster: cambia cuando cambia la inscripción del evento.
        return [
            'v' => $entries->count(),
            'rows' => $rows,
        ];
    }
}
