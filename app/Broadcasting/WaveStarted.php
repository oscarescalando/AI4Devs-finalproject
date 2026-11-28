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
 * `wave.started` (ISSUE-141, TQ-017) — salida real de una oleada confirmada.
 *
 * Reglas de ADR-013 que este evento encarna:
 *
 * · Persistir antes de emitir: se despacha desde MarkWaveStart DESPUÉS del
 *   `save()` de `actual_start_at`; nunca antes.
 * · Cola y afterCommit: implements ShouldBroadcast (el BroadcastEvent va a
 *   la cola `broadcast`, lo emite un worker de Horizon) y
 *   ShouldDispatchAfterCommit (el dispatcher retiene la emisión hasta el
 *   commit de la transacción en curso: si hay rollback, no se emite nada).
 * · Canal: DOS variantes como MarkRecorded (RF-75). La ops (`$public =
 *   false`, por defecto) va al privado `event.{ulid}.ops` con {eventUlid,
 *   waveUlid, actualStartAt, seq}; la pública (`$public = true`, TQ-032)
 *   va al canal ABIERTO `event.{ulid}.public` con el subconjunto SIN PII
 *   {eventUlid, seq, actualStartAt} — el reloj de la pública solo necesita
 *   la hora oficial de salida para arrancar el cronómetro de carrera; el
 *   waveUlid (detalle operativo) queda solo en ops: subconjunto estricto
 *   como en MarkRecorded (INV-06).
 *
 * `seq` (secuencia por evento para resincronizarse tras reconectar) queda
 * en null hasta que TQ-018 fije el contrato v/seq del leaderboard.
 *
 * TQ-019: el broadcast es best-effort (INV-08) — ShouldRescue reporta un
 * fallo al encolar sin romper la petición que ya persistió la salida.
 */
final class WaveStarted implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Cola dedicada de broadcast (ADR-013: el request nunca bloquea por el WS). */
    public string $queue = 'broadcast';

    /**
     * @param  string  $eventUlid  ULID público del evento (ADR-015)
     * @param  string  $waveUlid  ULID público de la oleada
     * @param  int  $actualStartAtMs  hora real de salida en milisegundos de época
     *                                (mismo convenio ms de server_now, RF-42)
     * @param  int|null  $seq  secuencia de resync (null: sin contrato aún)
     * @param  bool  $public  true = variante de canal público sin PII (TQ-032)
     */
    public function __construct(
        public readonly string $eventUlid,
        public readonly string $waveUlid,
        public readonly int $actualStartAtMs,
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
        return 'wave.started';
    }

    /**
     * El payload público es subconjunto ESTRICTO del de ops (INV-06):
     * identificadores públicos y la hora en ms, sin el detalle de oleada.
     *
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        if ($this->public) {
            return [
                'eventUlid' => $this->eventUlid,
                'seq' => $this->seq,
                'actualStartAt' => $this->actualStartAtMs,
            ];
        }

        return [
            'eventUlid' => $this->eventUlid,
            'waveUlid' => $this->waveUlid,
            'actualStartAt' => $this->actualStartAtMs,
            'seq' => $this->seq,
        ];
    }
}
