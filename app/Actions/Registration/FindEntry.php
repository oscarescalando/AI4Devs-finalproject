<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;

/**
 * Una inscripción de un evento por su ULID público (ISSUE-162/TQ-030): la
 * consulta cruza por el evento del path para que un ULID de otro evento
 * nunca resuelva (ADR-015). Alimenta cancelar/reactivar desde el listado.
 */
final readonly class FindEntry
{
    public function __invoke(string $eventUlid, string $entryUlid): ?Entry
    {
        $entry = Entry::query()
            ->with(['person', 'team'])
            ->where('ulid', $entryUlid)
            ->whereRelation('event', 'ulid', $eventUlid)
            ->first();

        return $entry instanceof Entry ? $entry : null;
    }
}
