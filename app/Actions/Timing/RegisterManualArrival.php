<?php

declare(strict_types=1);

namespace App\Actions\Timing;

use App\Models\Event;
use App\Models\TimingMark;
use App\Support\FormatClock;
use Carbon\CarbonImmutable;

/**
 * ISSUE-130 (TQ-012) — Captura manual de llegada: el juez teclea un dorsal
 * (+ Enter) y esta Action lo convierte en marca por el conducto único —
 * delega en RecordTimingMarkBatch con un lote de UNA marca y un
 * client_mark_id UUIDv7 generado por el servidor, heredando toda la
 * semántica: asignación por (event_id, bib), huérfana, idempotencia por
 * client_mark_id (INV-02), transacción del lote y recálculo afterCommit
 * (INV-08). La hora "del dispositivo" es el reloj del servidor (offset 0).
 *
 * Doble llegada del mismo dorsal: responde duplicate SIN escribir una
 * segunda fila (el «ya registrado» de la paridad). La ingesta cruda de las
 * estaciones no se toca: seguir siendo append-only y admitir relecturas.
 */
final readonly class RegisterManualArrival
{
    public function __construct(private RecordTimingMarkBatch $batch) {}

    /**
     * @param  int|null  $bib  dorsal tecleado; null = llegada sin dorsal (RF-38, sin teclear «0»).
     * @return array{status: string, bib: int|null, athlete: string|null, club: string|null, time: string, client_mark_id: string}
     */
    public function __invoke(Event $event, ?int $bib): array
    {
        // ¿Este dorsal ya llegó? La captura manual no repite filas.
        $previous = $bib !== null
            ? TimingMark::query()
                ->where('event_id', $event->id)
                ->whereHas('entry', fn ($query) => $query->where('bib', $bib))
                ->orderBy('captured_at')
                ->first()
            : null;

        if ($previous !== null) {
            $previous->load('entry.person', 'entry.team.organization');

            return $this->payload('duplicate', $bib, $previous, $event->timezone);
        }

        $clientMarkId = (string) str()->uuid7();
        $now = CarbonImmutable::now(config('app.timezone'));

        [$result] = ($this->batch)($event->ulid, [[
            'client_mark_id' => $clientMarkId,
            'bib' => $bib,
            'device_time_ms' => $now->getTimestampMs(),
            'clock_offset_ms' => 0, // el «dispositivo» es el propio servidor
            'source' => 'manual',
        ]]);

        $mark = TimingMark::query()
            ->where('client_mark_id', $clientMarkId)
            ->with('entry.person', 'entry.team.organization')
            ->firstOrFail();

        return $this->payload((string) $result['status'], $bib, $mark, $event->timezone);
    }

    /**
     * VO de respuesta para la pantalla: estado, dorsal tecleado, nombre y
     * club públicos del atleta (INV-06) y la hora de la marca.
     *
     * @return array{status: string, bib: int|null, athlete: string|null, club: string|null, time: string, client_mark_id: string}
     */
    private function payload(string $status, ?int $bib, TimingMark $mark, string $timezone): array
    {
        $entry = $mark->entry;

        return [
            'status' => $status,
            'bib' => $bib,
            'athlete' => $entry?->person?->publicName() ?? $entry?->team?->name,
            'club' => $entry?->team?->organization?->name,
            'time' => FormatClock::timeOfDay($mark->captured_at, $timezone),
            'client_mark_id' => $mark->client_mark_id,
        ];
    }
}
