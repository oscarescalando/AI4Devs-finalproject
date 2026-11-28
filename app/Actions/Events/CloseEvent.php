<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Actions\Results\RecalculateEventResults;
use App\Broadcasting\EventClosed;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ISSUE-163 (TQ-034) — Cierre/publicación del evento: la operación que
 * OFICIALIZA. Pasa events.status a `closed` y fuerza el recálculo de forma
 * SÍNCRONA (no en cola): el cierre no responde a una marca, ES la palabra
 * final del operador — al terminar la Action la clasificación ya quedó
 * `official` y conciliada con lo que se mostró en vivo (INV-07; la
 * conciliación la prueba RecalculateResultsTest). El job de recálculo por
 * marca queda intacto: durante el vivo sigue siendo en cola (INV-08).
 *
 * Guardas: las evalúa PreviewCloseEvent (única fuente) y se rechaza con el
 * PRIMER blocker en su orden — evento ya cerrado, fuera de live/finished,
 * sin oleadas, oleada sin salida real (nombrada) o cero llegadas. Sin
 * guardas cumplidas no se toca el estado.
 *
 * Emisión: `event.closed` (EventClosed) en sus DOS variantes de canal (ops
 * y pública sin PII) DENTRO de la transacción — ShouldDispatchAfterCommit
 * las retiene hasta el commit (rollback = nada emitido, ADR-013) y van a
 * la cola `broadcast`. La pública es la señal de modo OFICIAL para el
 * leaderboard/kiosco; el sondeo de 15 s la recoge igual (RF-76).
 */
final readonly class CloseEvent
{
    /**
     * @return array{status: string, results: int} estado final y número de
     *                                             resultados oficiales escritos
     */
    public function __invoke(Event $event): array
    {
        $preview = app(PreviewCloseEvent::class)($event);

        if (! $preview['can']) {
            /** @var list<string> $blockers PreviewCloseEvent las llena ordenadas. */
            $blockers = $preview['blockers'];

            throw ValidationException::withMessages(['event' => $blockers[0]]);
        }

        $written = DB::transaction(function () use ($event): int {
            $event->forceFill(['status' => 'closed'])->save();

            // Síncrono a propósito: el recálculo lee el estado NUEVO (closed)
            // y escribe results con status `official` en la MISMA transacción
            // — si revienta, el cierre revierte completo.
            $written = (new RecalculateEventResults)($event->id);

            $this->announce($event->ulid);

            return $written;
        });

        return ['status' => 'closed', 'results' => $written];
    }

    /**
     * Emisión del cierre (calco del dual de MarkWaveStart::announce):
     * variante ops y variante pública con el mismo payload sin PII.
     * ShouldDispatchAfterCommit retiene ambas hasta el commit.
     */
    private function announce(string $eventUlid): void
    {
        $closedAtMs = (int) CarbonImmutable::now(config('app.timezone'))->getTimestampMs();

        EventClosed::dispatch(
            eventUlid: $eventUlid,
            closedAtMs: $closedAtMs,
        );

        EventClosed::dispatch(
            eventUlid: $eventUlid,
            closedAtMs: $closedAtMs,
            public: true,
        );
    }
}
