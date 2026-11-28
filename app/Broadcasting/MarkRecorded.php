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
 * `mark.recorded` (ISSUE-141, TQ-018) — una marca nueva persistida en el
 * lote (RecordTimingMarkBatch: estación o captura manual).
 *
 * Reglas de ADR-013 que este evento encarna:
 *
 * · Persistir antes de emitir: se despacha DESPUÉS del `create()` de la
 *   marca y, al ser ShouldDispatchAfterCommit, el dispatcher retiene la
 *   emisión hasta el commit del lote — un rollback no emite nada. Las
 *   marcas `duplicate` (reenvío idempotente, INV-02) NO emiten: no hay
 *   dato nuevo que anunciar.
 * · Granularidad: una marca → un evento; un lote de N marcas nuevas → N
 *   eventos. Decisión simple y documentada: el job de broadcast es barato
 *   (payload diminuto, cola `broadcast` dedicada) y así cada pantalla
 *   refresca exactamente cuando llega su dato, sin acumular deltas.
 *
 * DOS canales con payload DISTINTO (RF-75):
 *
 * · variante ops (`$public = false`, la por defecto): canal privado
 *   `event.{ulid}.ops` con {eventUlid, seq, bib, name, capturedAtMs,
 *   status} — detalle completo para el equipo operativo.
 * · variante pública (`$public = true`): canal ABIERTO
 *   `event.{ulid}.public` con SOLO {eventUlid, seq, bib, capturedAtMs} —
 *   sin nombre ni club ni estado (INV-06): el feed público jamás filtra
 *   PII aunque el payload se inspeccione en el cable.
 *
 * `seq` = id autoincremental de la marca (monotónico por BD): permite a
 * las pantallas descartar eventos repetidos/viejos tras reconectar.
 */
final class MarkRecorded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Cola dedicada de broadcast (ADR-013: el request nunca bloquea por el WS). */
    public string $queue = 'broadcast';

    /**
     * @param  string  $eventUlid  ULID público del evento (ADR-015)
     * @param  int  $seq  id creciente de la marca (timing_marks.id)
     * @param  int|null  $bib  dorsal de la inscripción asignada; null = huérfana
     * @param  int  $capturedAtMs  hora oficial de la marca en ms de época (INV-10)
     * @param  string|null  $name  nombre público del atleta (solo variante ops)
     * @param  string  $status  assigned|orphan (solo variante ops)
     * @param  bool  $public  true = variante de canal público sin PII
     */
    public function __construct(
        public readonly string $eventUlid,
        public readonly int $seq,
        public readonly ?int $bib,
        public readonly int $capturedAtMs,
        public readonly ?string $name = null,
        public readonly string $status = 'orphan',
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
        return 'mark.recorded';
    }

    /**
     * El payload público es subconjunto ESTRICTO del de ops (INV-06).
     *
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'eventUlid' => $this->eventUlid,
            'seq' => $this->seq,
            'bib' => $this->bib,
            'capturedAtMs' => $this->capturedAtMs,
        ];

        if ($this->public) {
            return $payload;
        }

        return [...$payload, 'name' => $this->name, 'status' => $this->status];
    }
}
