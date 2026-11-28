<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Wave;
use Illuminate\Support\Collection;

/**
 * ISSUE-163 (TQ-034) — Previsualización del cierre: evalúa las guardas del
 * cierre SIN mutar nada y devuelve {can, blockers} para que la confirmación
 * del hub muestre exactamente qué falta antes de ejecutar CloseEvent.
 *
 * Es la ÚNICA fuente de las guardas: CloseEvent la invoca y rechaza con el
 * primer blocker (mismo orden), así la pantalla y el servidor jamás
 * divergen sobre qué permite cerrar (ADR-011: la UI informa; el servidor
 * autoriza).
 *
 * Guardas, en orden:
 * 1. ya cerrado → blocker propio (el cierre es idempotente a la defensiva);
 * 2. fuera de live/finished → blocker accionable (cerrar tiene sentido al
 *    terminar la operación; draft/registration se configuran primero);
 * 3. sin oleadas → blocker accionable (tique TQ-034: eventos sin carreras);
 * 4. alguna oleada sin salida real → blocker NOMBRANDO la oleada (carrera ·
 *    oleada) en el orden del tablero de salidas;
 * 5. cero marcas del evento → blocker accionable (sin llegadas no hay nada
 *    que oficializar).
 */
final readonly class PreviewCloseEvent
{
    /**
     * @return array{can: bool, blockers: list<string>}
     */
    public function __invoke(Event $event): array
    {
        $blockers = [];

        if ($event->status === 'closed') {
            $blockers[] = __('ui.events.close_err_closed');

            return ['can' => false, 'blockers' => $blockers];
        }

        if (! in_array($event->status, ['live', 'finished'], true)) {
            $blockers[] = __('ui.events.close_err_not_live', ['status' => __('ui.events.status.'.$event->status)]);

            return ['can' => false, 'blockers' => $blockers];
        }

        $ordered = $this->orderedWaves($event);

        if ($ordered->isEmpty()) {
            $blockers[] = __('ui.events.close_err_no_waves');

            return ['can' => false, 'blockers' => $blockers];
        }

        /** @var Wave|null $pending */
        $pending = $ordered->first(fn (Wave $wave): bool => $wave->actual_start_at === null);

        if ($pending !== null) {
            $blockers[] = __('ui.events.close_err_wave_pending', ['name' => $this->label($pending)]);
        }

        if ($event->timingMarks()->count() === 0) {
            $blockers[] = __('ui.events.close_err_no_arrivals');
        }

        return ['can' => $blockers === [], 'blockers' => $blockers];
    }

    /**
     * Oleadas del evento en el orden del tablero (ISSUE-132), igual que
     * MarkWaveStart: el blocker nombra la PRIMERA pendiente que vería el
     * operador en la pantalla de salidas.
     *
     * @return Collection<int, Wave>
     */
    private function orderedWaves(Event $event): Collection
    {
        return Wave::query()
            ->whereRelation('race', 'event_id', $event->id)
            ->with('race')
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get();
    }

    /** «Carrera · Oleada»: nombre accionable (el «Única» solo se desambigua con su carrera). */
    private function label(Wave $wave): string
    {
        $name = $wave->name ?? __('ui.events.start_wave_fallback');

        $raceName = (string) $wave->race->name;

        return $raceName !== '' ? $raceName.' · '.$name : $name;
    }
}
