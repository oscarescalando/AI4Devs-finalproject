<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;
use Illuminate\Validation\ValidationException;

/**
 * Reactiva una inscripción cancelada (ISSUE-162/TQ-030): vuelve al estado
 * `registered` con su dorsal y categoría intactos — el camino de retorno de
 * la baja lógica, sin recrear la fila (el UNIQUE event+person habría
 * bloqueado un alta nueva de la misma persona).
 *
 * @throws ValidationException
 */
final readonly class ReactivateEntry
{
    public function __invoke(string $eventUlid, string $entryUlid): Entry
    {
        $entry = app(FindEntry::class)($eventUlid, $entryUlid);

        if ($entry === null) {
            throw ValidationException::withMessages([
                'entry' => __('ui.events.entry_err_missing'),
            ]);
        }

        if ($entry->status !== 'cancelled') {
            throw ValidationException::withMessages([
                'entry' => __('ui.events.entry_err_not_cancelled'),
            ]);
        }

        $entry->update(['status' => 'registered']);

        return $entry;
    }
}
