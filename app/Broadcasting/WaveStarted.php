<?php

declare(strict_types=1);

namespace App\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
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
 * · Canal: SOLO `event.{ulid}.ops` (privado). La pantalla pública de hoy no
 *   muestra salidas (solo clasificación por llegadas), así que no se emite
 *   al feed público todavía; cuando ISSUE-141/TQ-018 conecte el reloj de la
 *   pública, se añade `event.{ulid}.public` con el mismo payload sin PII.
 *
 * `seq` (secuencia por evento para resincronizarse tras reconectar) queda
 * en null hasta que TQ-018 fije el contrato v/seq del leaderboard.
 */
final class WaveStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
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
     */
    public function __construct(
        public readonly string $eventUlid,
        public readonly string $waveUlid,
        public readonly int $actualStartAtMs,
        public readonly ?int $seq = null,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("event.{$this->eventUlid}.ops"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'wave.started';
    }

    /**
     * Payload sin PII: identificadores públicos y la hora en ms.
     *
     * @return array{eventUlid: string, waveUlid: string, actualStartAt: int, seq: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'eventUlid' => $this->eventUlid,
            'waveUlid' => $this->waveUlid,
            'actualStartAt' => $this->actualStartAtMs,
            'seq' => $this->seq,
        ];
    }
}
