<?php

declare(strict_types=1);

namespace App\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `results.updated` (ISSUE-141, TQ-018) — el motor terminó de recalcular
 * la clasificación de un evento y la tabla derivada `results` ya tiene la
 * versión nueva.
 *
 * Se emite desde RecalculateEventResultsJob DESPUÉS de que el Action
 * termina con éxito (la emisión vive en el job, no dentro del SQL del
 * Action — así el Action sigue siendo un recálculo puro y reutilizable).
 * El job corre en cola, fuera de toda transacción: el job ES el commit
 * boundary, así que el ShouldDispatchAfterCommit encola la emisión de
 * inmediato en `broadcast`. Si el Action revienta, el job falla y no se
 * emite nada.
 *
 * DOS canales con el MISMO payload (RF-75): `event.{ulid}.public`
 * (abierto) y `event.{ulid}.ops` (privado). El payload no lleva PII —
 * solo la versión y el estado de la clasificación; los datos los vuelven
 * a leer las pantallas por sus Actions (INV-07: nada de resultados por el
 * cable, solo la señal de que cambiaron).
 *
 * `seq` = MAX(results.id) de las filas del evento tras el recálculo
 * (0 si el evento quedó sin resultados): monótono por evento porque el
 * motor reescribe results (delete + insert) y el autoincremental jamás
 * retrocede — cada recálculo produce una versión estrictamente mayor
 * mientras el motor corra sobre el mismo evento.
 */
final class ResultsUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Cola dedicada de broadcast (ADR-013: el request nunca bloquea por el WS). */
    public string $queue = 'broadcast';

    /**
     * @param  string  $eventUlid  ULID público del evento (ADR-015)
     * @param  int  $seq  versión creciente del recálculo (MAX results.id del evento)
     * @param  string  $status  provisional|official tal como quedó en results
     */
    public function __construct(
        public readonly string $eventUlid,
        public readonly int $seq,
        public readonly string $status,
    ) {}

    /**
     * @return list<Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("event.{$this->eventUlid}.public"),
            new PrivateChannel("event.{$this->eventUlid}.ops"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'results.updated';
    }

    /**
     * @return array{eventUlid: string, seq: int, status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'eventUlid' => $this->eventUlid,
            'seq' => $this->seq,
            'status' => $this->status,
        ];
    }
}
