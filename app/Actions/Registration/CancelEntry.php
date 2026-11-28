<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;
use Illuminate\Validation\ValidationException;

/**
 * Baja lógica de una inscripción (ISSUE-162/TQ-030): el estado pasa a
 * `cancelled` y la fila NUNCA se borra — conserva dorsal, categoría e
 * historia para el informe del evento (y el UNIQUE event+person sigue
 * impidiendo duplicar a la persona: se reactiva, no se re-inscribe).
 *
 * @throws ValidationException
 */
final readonly class CancelEntry
{
    public function __invoke(string $eventUlid, string $entryUlid): Entry
    {
        $entry = app(FindEntry::class)($eventUlid, $entryUlid);

        if ($entry === null) {
            throw ValidationException::withMessages([
                'entry' => __('ui.events.entry_err_missing'),
            ]);
        }

        if ($entry->status === 'cancelled') {
            throw ValidationException::withMessages([
                'entry' => __('ui.events.entry_err_already_cancelled'),
            ]);
        }

        $entry->update(['status' => 'cancelled']);

        return $entry;
    }
}
