<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;

/**
 * Un evento por su ULID público con sus carreras (TQ-025/ISSUE-160): carga
 * el formulario de edición. El identificador entero nunca sale del backend
 * (ADR-015).
 */
final readonly class FindEvent
{
    public function __invoke(string $ulid): ?Event
    {
        $event = Event::query()
            ->with('races')
            ->where('ulid', $ulid)
            ->first();

        return $event instanceof Event ? $event : null;
    }
}
