<?php

declare(strict_types=1);

namespace App\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `event.closed` (ISSUE-163, TQ-034) — el evento cerró y su clasificación
 * quedó OFICIAL: CloseEvent pasÓ events.status a closed y forzó el
 * recálculo síncrono (el cierre es la operación que oficializa) ANTES de
 * emitir esto.
 *
 * Reglas de ADR-013 que este evento encarna (calco del dual de WaveStarted):
 *
 * · Persistir antes de emitir: se despacha desde CloseEvent DESPUÉS de
 *   guardar el estado y escribir los resultados oficiales; nunca antes.
 * · Cola y afterCommit: implements ShouldBroadcast (el BroadcastEvent va a
 *   la cola `broadcast`) y ShouldDispatchAfterCommit (el dispatcher retiene
 *   la emisión hasta el commit de la transacción en curso: si hay rollback,
 *   no se emite nada).
 * · Canal: DOS variantes. La ops (`$public = false`) va al privado
 *   `event.{ulid}.ops`; la pública (`$public = true`) va al canal ABIERTO
 *   `event.{ulid}.public` con el MISMO subconjunto SIN PII {eventUlid, seq,
 *   closedAt} — no hay dato operativo extra que reservar a ops, así que el
 *   payload es idéntico y el subconjunto estricto de INV-06 se cumple por
 *   construcción. La pública es la señal para que el leaderboard/kiosco
 *   refresque y pase a modo OFICIAL sin esperar al sondeo de 15 s.
 *
 * `seq` queda en null como en los eventos de oleada: el versionado fino de
 * la clasificación vive en `results.updated` (seq = MAX results.id); este
 * evento anuncia el cambio de FASE del evento, no una versión de results.
 *
 * ShouldRescue (INV-08): broadcast best-effort — un fallo al encolar no
 * tumba el cierre que ya persistió; las pantallas tienen el sondeo.
 */
final class EventClosed implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Cola dedicada de broadcast (ADR-013: el request nunca bloquea por el WS). */
    public string $queue = 'broadcast';

    /**
     * @param  string  $eventUlid  ULID público del evento (ADR-015)
     * @param  int  $closedAtMs  hora del cierre en milisegundos de época
     *                           (mismo convenio ms de server_now, RF-42)
     * @param  int|null  $seq  secuencia de resync (null: sin contrato, ver results.updated)
     * @param  bool  $public  true = variante de canal público sin PII
     */
    public function __construct(
        public readonly string $eventUlid,
        public readonly int $closedAtMs,
        public readonly ?int $seq = null,
        public readonly bool $public = false,
    ) {}

    /**
     * @return list<Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->public
            ? [new Channel("event.{$this->eventUlid}.public")]
            : [new PrivateChannel("event.{$this->eventUlid}.ops")];
    }

    public function broadcastAs(): string
    {
        return 'event.closed';
    }

    /**
     * Payload sin PII (INV-06): identificadores públicos y la hora en ms —
     * idéntico en ops y pública (no hay detalle operativo que agregar).
     *
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'eventUlid' => $this->eventUlid,
            'seq' => $this->seq,
            'closedAt' => $this->closedAtMs,
        ];
    }
}
