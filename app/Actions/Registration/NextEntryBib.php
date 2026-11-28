<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;

/**
 * Dorsal sugerido para la próxima inscripción (ISSUE-162/TQ-030): MAX(bib)+1
 * del evento (1 si aún no hay inscripciones). Es una SUGERENCIA que el
 * operador puede editar en el modal; la unicidad real (INV-03) la custodia
 * la base y la vuelve a validar CreateEntry al guardar.
 */
final readonly class NextEntryBib
{
    public function __invoke(string $eventUlid): int
    {
        $max = Entry::query()
            ->whereRelation('event', 'ulid', $eventUlid)
            ->max('bib');

        return (int) $max + 1;
    }
}
