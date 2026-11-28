<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Wave;

/**
 * Una oleada de un evento por su ULID público, con su carrera (TQ-026/
 * ISSUE-160): carga el formulario de edición. La consulta cruza por la
 * carrera → evento del path para que un ULID ajeno nunca resuelva (ADR-015).
 */
final readonly class FindWave
{
    public function __invoke(string $eventUlid, string $waveUlid): ?Wave
    {
        $wave = Wave::query()
            ->where('ulid', $waveUlid)
            ->whereRelation('race.event', 'ulid', $eventUlid)
            ->with('race')
            ->first();

        return $wave instanceof Wave ? $wave : null;
    }
}
