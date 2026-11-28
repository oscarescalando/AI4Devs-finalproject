<?php

declare(strict_types=1);

namespace App\Actions\Results;

use App\Models\Event;
use App\Support\EffectiveMarks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ISSUE-158 — Motor de resultados del servidor (INV-07).
 *
 * Recalcula la tabla derivada `results` de un evento desde las marcas
 * confirmadas: net_ms = primera llegada − salida real de la oleada +
 * Σ ajustes acumulados, con posiciones general/género/categoría por
 * RANK() OVER dentro de cada carrera. Solo este motor escribe results;
 * nadie edita resultados a mano.
 *
 * La marca es append-only (INV-01): la "primera llegada" de una
 * inscripción es MIN(captured_at) sobre sus marcas EFECTIVAS — la marca
 * jamás se toca; su ÚLTIMA corrección decide (void fuera, reassign
 * re-atribuido al dorsal destino, adjust con su offset) mediante la
 * derivación compartida de EffectiveMarks (TQ-014, ISSUE-135).
 */
final class RecalculateEventResults
{
    /**
     * @return int número de resultados escritos
     */
    public function __invoke(int $eventId): int
    {
        $event = Event::query()->findOrFail($eventId);

        // provisional en vivo; oficial cuando el evento cierra (conciliación)
        $resultStatus = in_array($event->status, ['finished', 'closed'], true) ? 'official' : 'provisional';

        // Estado efectivo en SQL (EffectiveMarks): la aritmética queda en la
        // base y no se hidratan marcas en PHP para el cálculo masivo.
        $effective = EffectiveMarks::query($eventId);
        $arrivals = EffectiveMarks::arrivalsFromEffective();

        // net_ms en SQL con aritmética que no desborda: TIMESTAMPDIFF(MICROSECOND)
        // desborda enteros de MySQL a partir de ~36 minutos, así que se combina
        // la parte entera de segundos con la fracción de milisegundos.
        $ranked = DB::select(
            <<<SQL
            WITH effective AS ({$effective->toSql()}),
            arrivals AS ({$arrivals->toSql()}),
            net AS (
                SELECT
                    e.id AS entry_id,
                    e.race_id,
                    e.category_id,
                    p.gender,
                    TIMESTAMPDIFF(SECOND, w.actual_start_at, a.first_arrival) * 1000
                        + (MICROSECOND(a.first_arrival) DIV 1000)
                        - (MICROSECOND(w.actual_start_at) DIV 1000)
                        + COALESCE(adj.total_ms, 0) AS net_ms
                FROM entries e
                JOIN waves w ON w.id = e.wave_id AND w.actual_start_at IS NOT NULL
                JOIN arrivals a ON a.entry_id = e.id
                LEFT JOIN persons p ON p.id = e.person_id
                LEFT JOIN (
                    SELECT entry_id, SUM(delta_ms) AS total_ms
                    FROM entry_adjustments
                    GROUP BY entry_id
                ) adj ON adj.entry_id = e.id
                WHERE e.event_id = ? AND e.status <> 'withdrawn'
            ),
            ranked AS (
                SELECT
                    entry_id,
                    net_ms,
                    RANK() OVER (PARTITION BY race_id ORDER BY net_ms ASC) AS rank_general,
                    RANK() OVER (PARTITION BY race_id, gender ORDER BY net_ms ASC) AS rank_gender,
                    RANK() OVER (PARTITION BY race_id, category_id ORDER BY net_ms ASC) AS rank_category
                FROM net
            )
            SELECT entry_id, net_ms, rank_general, rank_gender, rank_category
            FROM ranked
            SQL,
            [...$effective->getBindings(), $eventId],
        );

        $now = now()->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($event, $ranked, $resultStatus, $now): int {
            // results es derivada: el recálculo reemplaza lo anterior del evento.
            DB::table('results')->whereIn('entry_id', $event->entries()->pluck('id'))->delete();

            if ($ranked === []) {
                return 0;
            }

            $rows = array_map(
                static fn ($r): array => [
                    'ulid' => strtolower((string) Str::ulid()),
                    'entry_id' => (int) $r->entry_id,
                    'net_ms' => (int) $r->net_ms,
                    'rank_general' => (int) $r->rank_general,
                    'rank_gender' => $r->rank_gender === null ? null : (int) $r->rank_gender,
                    'rank_category' => (int) $r->rank_category,
                    'points' => null, // liga: v2
                    'status' => $resultStatus,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $ranked,
            );

            DB::table('results')->insert($rows);

            return count($rows);
        });
    }
}
