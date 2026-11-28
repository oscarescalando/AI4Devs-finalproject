<?php

declare(strict_types=1);

namespace App\Actions\Timing;

use App\Broadcasting\MarkRecorded;
use App\Jobs\Results\RecalculateEventResultsJob;
use App\Models\Entry;
use App\Models\Event;
use App\Models\TimingMark;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ISSUE-156 — Endpoint idempotente de marcas del servidor.
 *
 * Recibe un lote (<= 100 marcas), deduplica por client_mark_id (INV-02),
 * asigna cada marca a su inscripción por (event_id, bib) o la deja huérfana,
 * y responde por marca: assigned / orphan / duplicate. Todo el lote dentro
 * de una única transacción: o se persiste completo o nada.
 *
 * TQ-018: cada marca NUEVA emite `mark.recorded` en sus dos variantes de
 * canal (ops con detalle, público sin PII — MarkRecorded). Al ser
 * ShouldDispatchAfterCommit, el dispatcher retiene las emisiones hasta el
 * commit del lote; los `duplicate` no emiten (no hay dato nuevo).
 */
final class RecordTimingMarkBatch
{
    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, int|string|null>>
     */
    public function __invoke(string $eventUlid, array $marks): array
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $results = DB::transaction(function () use ($event, $marks): array {
            $requested = collect($marks)->keyBy('client_mark_id');

            // Lo que ya existe (reenvío) responde duplicate con su resultado persistido.
            $existing = TimingMark::query()
                ->where('event_id', $event->id)
                ->whereIn('client_mark_id', $requested->keys())
                ->with('entry')
                ->get()
                ->keyBy('client_mark_id');

            $bibList = $requested
                ->map(fn (array $m) => is_int($m['bib'] ?? null) ? $m['bib'] : null)
                ->filter()
                ->all();
            /** @var Collection<int, Entry> $entriesByBib */
            $entriesByBib = Entry::query()
                ->where('event_id', $event->id)
                ->whereIn('bib', $bibList)
                ->with(['wave', 'adjustments', 'person', 'team'])
                ->get()
                ->keyBy('bib');

            $results = [];

            foreach ($marks as $mark) {
                $clientMarkId = (string) $mark['client_mark_id'];

                $persisted = $existing->get($clientMarkId);
                if ($persisted !== null) {
                    $results[] = $this->resultFor($persisted, 'duplicate');

                    continue;
                }

                $bib = is_int($m = $mark['bib'] ?? null) ? $m : null;
                $entry = $bib !== null ? $entriesByBib->get($bib) : null;

                $created = TimingMark::query()->create([
                    'event_id' => $event->id,
                    'entry_id' => $entry?->id,
                    'client_mark_id' => $clientMarkId,
                    'captured_at' => $this->officialTime($mark),
                    'device_time_ms' => (int) $mark['device_time_ms'],
                    'source' => (string) ($mark['source'] ?? 'station'),
                ]);

                // ADR-013/TQ-018: persistir antes de emitir — una marca
                // nueva anuncia su llegada en ops (detalle del juez) y en
                // el feed público (solo dorsal y hora, INV-06). El
                // afterCommit retiene ambas hasta el commit del lote.
                $this->announce($event->ulid, $created, $bib, $entry);

                $results[] = $this->resultFor($created, $entry !== null ? 'assigned' : 'orphan');
            }

            return $results;
        });

        // Recalculo de la clasificación en cola, tras el commit: nunca
        // bloquea la respuesta de la marca (INV-08).
        RecalculateEventResultsJob::dispatch($event->id)->afterCommit();

        return $results;
    }

    /**
     * Hora oficial de la marca (INV-10): reloj del dispositivo corregido
     * por el offset calculado contra el reloj del servidor.
     *
     * @param  array<string, mixed>  $mark
     */
    private function officialTime(array $mark): CarbonImmutable
    {
        $correctedMs = (int) $mark['device_time_ms'] + (int) ($mark['clock_offset_ms'] ?? 0);

        return CarbonImmutable::createFromTimestampMs($correctedMs, config('app.timezone'));
    }

    /**
     * Emisión de la marca nueva (TQ-018): variante ops (con nombre público
     * y estado) y variante pública (sin PII, RF-75/INV-06). `seq` = id de
     * la marca: creciente y deduplicable en las pantallas.
     */
    private function announce(string $eventUlid, TimingMark $mark, ?int $bib, ?Entry $entry): void
    {
        $capturedAtMs = (int) $mark->captured_at->getTimestampMs();
        $name = $entry?->person?->publicName() ?? $entry?->team?->name;
        $status = $entry !== null ? 'assigned' : 'orphan';

        MarkRecorded::dispatch(
            eventUlid: $eventUlid,
            seq: (int) $mark->id,
            bib: $bib,
            capturedAtMs: $capturedAtMs,
            name: $name,
            status: $status,
        );

        MarkRecorded::dispatch(
            eventUlid: $eventUlid,
            seq: (int) $mark->id,
            bib: $bib,
            capturedAtMs: $capturedAtMs,
            public: true,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function resultFor(TimingMark $mark, string $status): array
    {
        $entry = $mark->entry;

        return [
            'client_mark_id' => $mark->client_mark_id,
            'status' => $status,
            'entry_id' => $entry?->ulid,
            'net_ms' => $entry !== null && $entry->wave->actual_start_at !== null
                ? $this->netMs($mark, $entry)
                : null,
        ];
    }

    /**
     * net_ms = llegada − salida real + Σ ajustes (INV-07). Cálculo de
     * cortesía en la respuesta; el que manda es el job de resultados.
     */
    private function netMs(TimingMark $mark, Entry $entry): int
    {
        $start = $entry->wave->actual_start_at;
        if ($start === null) {
            return 0;
        }

        $adjustments = $entry->adjustments;

        $gross = (int) $mark->captured_at->diffInMilliseconds($start);

        return $gross + (int) $adjustments->sum('delta_ms');
    }
}
