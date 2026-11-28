<?php

declare(strict_types=1);

namespace App\Jobs\Results;

use App\Actions\Results\RecalculateEventResults;
use App\Broadcasting\ResultsUpdated;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Recalculo de la clasificación al llegar cada marca: en cola y
 * afterCommit (INV-08) para que nunca bloquee la captura — la marca
 * responde aunque este job espere su turno.
 *
 * TQ-018: al terminar el recálculo con éxito se emite `results.updated`
 * (ResultsUpdated) en public y ops. La emisión vive AQUÍ y no dentro del
 * SQL del Action: el Action sigue siendo un recálculo puro (reutilizable
 * por comandos/futuras vías) y el job es quien sabe que la versión nueva
 * ya quedó visible. El job corre en cola, fuera de toda transacción: el
 * job ES el commit boundary, así que el ShouldDispatchAfterCommit encola
 * la emisión de inmediato en `broadcast` (emisión directa, sin job
 * intermediario). Si el Action lanza, el job falla y no se emite nada.
 */
final class RecalculateEventResultsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public function __construct(public readonly int $eventId) {}

    public function handle(RecalculateEventResults $action): void
    {
        $action($this->eventId);

        $event = Event::query()->findOrFail($this->eventId);

        // Versión creciente del recálculo: MAX(results.id) del evento (0
        // si quedó sin resultados) — documentado en ResultsUpdated.
        $meta = DB::table('results')
            ->whereIn('entry_id', $event->entries()->pluck('id'))
            ->selectRaw('COALESCE(MAX(id), 0) as seq, MAX(status) as status')
            ->first();

        ResultsUpdated::dispatch(
            $event->ulid,
            (int) ($meta->seq ?? 0),
            (string) ($meta->status ?? 'provisional'),
        );
    }
}
