<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Wave;
use Illuminate\Validation\ValidationException;

/**
 * Elimina una oleada del evento por su ULID (TQ-026/ISSUE-160): bloqueada
 * cuando tiene inscripciones — entries.wave_id es FK restrict y una oleada
 * con corredores no se destruye. Error de negocio con nombre legible que la
 * pantalla pinta como toast; la salida real no implica nada aquí.
 */
final readonly class DeleteWave
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, string $waveUlid): void
    {
        $wave = Wave::query()
            ->where('ulid', $waveUlid)
            ->whereRelation('race.event', 'ulid', $eventUlid)
            ->firstOrFail();

        if ($wave->entries()->exists()) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.wave_err_has_entries', ['name' => $wave->name ?? __('ui.events.start_wave_fallback')]),
            ]);
        }

        $wave->delete();
    }
}
