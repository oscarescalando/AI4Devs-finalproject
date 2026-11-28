<?php

declare(strict_types=1);

namespace App\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `wave.undone` (ISSUE-141, TQ-018) — se deshizo la salida real de una
 * oleada (UndoWaveStart): `actual_start_at` volvió a null y la oleada
 * regresa a Pendiente.
 *
 * Espejo exacto de WaveStarted (misma pauta de ADR-013): se despacha
 * DESPUÉS del `save()`, ShouldBroadcast lo lleva a la cola `broadcast` y
 * ShouldDispatchAfterCommit retiene la emisión hasta el commit (rollback
 * = nada emitido). Canal: SOLO `event.{ulid}.ops` (privado) — igual que
 * wave.started, la pantalla pública no muestra salidas.
 *
 * `seq` queda en null (contrato v/seq mínimo): los eventos de oleada no
 * versionan datos derivados — son señales de «el tablero cambió» y las
 * pantallas refrescan al recibirlos.
 *
 * TQ-019: el broadcast es best-effort (INV-08) — ShouldRescue reporta un
 * fallo al encolar sin romper la petición que ya persistió el deshacer.
 */
final class WaveUndone implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Cola dedicada de broadcast (ADR-013: el request nunca bloquea por el WS). */
    public string $queue = 'broadcast';

    /**
     * @param  string  $eventUlid  ULID público del evento (ADR-015)
     * @param  string  $waveUlid  ULID público de la oleada deshecha
     */
    public function __construct(
        public readonly string $eventUlid,
        public readonly string $waveUlid,
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
        return 'wave.undone';
    }

    /**
     * Payload sin PII: identificadores públicos.
     *
     * @return array{eventUlid: string, waveUlid: string, seq: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'eventUlid' => $this->eventUlid,
            'waveUlid' => $this->waveUlid,
            'seq' => $this->seq,
        ];
    }
}
