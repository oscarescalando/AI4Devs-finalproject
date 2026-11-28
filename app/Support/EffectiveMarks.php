<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Derivación del ESTADO EFECTIVO de las marcas de un evento (TQ-014,
 * ISSUE-134/135). La marca es append-only (INV-01) y jamás se toca, así que
 * el estado con el que corren el motor de resultados y la bitácora se
 * DERIVA: para cada timing_mark decide su ÚLTIMA corrección
 * (MAX(id) de timing_mark_corrections):
 *
 *   void     → anulada: fuera del cálculo de llegadas
 *   reassign → la llegada se atribuye al entry del dorsal destino
 *              (before_after.after.bib, resuelto por (event_id, bib))
 *   adjust   → captured_at desplazado por el offset TOTAL de esa corrección
 *              (before_after.after.delta_ms, desde la marca cruda)
 *   sin corrección → la marca tal cual
 *
 * Única fuente de esa lógica: RecalculateEventResults la incrusta como CTE
 * `effective` del cálculo y las Actions de corrección/bitácora la usan como
 * tabla derivada. La primera llegada por entry es MIN sobre estas efectivas
 * (compartida aquí para que motor y bitácora no diverjan).
 */
final class EffectiveMarks
{
    /**
     * Marcas efectivas del evento. Columnas derivadas: effective_entry_id,
     * effective_captured_at, is_voided, last_correction_id, last_operation.
     */
    public static function query(int $eventId): Builder
    {
        $lastCorrection = DB::table('timing_mark_corrections AS tc')
            ->joinSub(
                DB::table('timing_mark_corrections')
                    ->select('timing_mark_id')
                    ->addSelect(DB::raw('MAX(id) AS last_id'))
                    ->groupBy('timing_mark_id'),
                'latest',
                'latest.last_id',
                '=',
                'tc.id',
            )
            ->select(['tc.id', 'tc.timing_mark_id', 'tc.operation', 'tc.before_after']);

        return DB::table('timing_marks AS tm')
            ->leftJoinSub($lastCorrection, 'lc', 'lc.timing_mark_id', '=', 'tm.id')
            ->leftJoin('entries AS target', function (JoinClause $join): void {
                $join->on('target.event_id', '=', 'tm.event_id')
                    ->on('target.bib', '=', DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(lc.before_after, '$.after.bib')) AS UNSIGNED)"));
            })
            ->where('tm.event_id', $eventId)
            ->select(['tm.id', 'tm.ulid', 'tm.source', 'tm.captured_at', 'tm.entry_id'])
            ->addSelect(DB::raw('lc.id AS last_correction_id'))
            ->addSelect(DB::raw('lc.operation AS last_operation'))
            ->addSelect(DB::raw("CASE WHEN lc.operation = 'void' THEN 1 ELSE 0 END AS is_voided"))
            ->addSelect(DB::raw("CASE WHEN lc.operation = 'reassign' THEN target.id ELSE tm.entry_id END AS effective_entry_id"))
            ->addSelect(DB::raw(
                "CASE WHEN lc.operation = 'adjust' "
                ."THEN tm.captured_at + INTERVAL (CAST(JSON_UNQUOTE(JSON_EXTRACT(lc.before_after, '$.after.delta_ms')) AS SIGNED) * 1000) MICROSECOND "
                .'ELSE tm.captured_at END AS effective_captured_at',
            ));
    }

    /**
     * Primera llegada efectiva por inscripción (MIN de las no anuladas del
     * entry): la misma «arrival» que consume el motor; la bitácora la usa
     * para marcar duplicadas (no ser la MIN del propio entry).
     */
    public static function firstArrivals(int $eventId): Builder
    {
        return self::arrivalAggregate(DB::query()->fromSub(self::query($eventId), 'em'))
            ->groupBy('em.effective_entry_id');
    }

    /**
     * Misma agregación de llegadas, pero leyendo de la CTE `effective` del
     * motor (FROM efectivo ya materializado, sin bindings propios).
     */
    public static function arrivalsFromEffective(): Builder
    {
        return self::arrivalAggregate(DB::table('effective AS em'))
            ->groupBy('em.effective_entry_id');
    }

    /**
     * Estado derivado de una marca para la bitácora (ISSUE-134): anulada >
     * huérfana > duplicada > corregida > asignada. Requiere los alias `em`
     * (EffectiveMarks::query) y `first` (firstArrivals) en la consulta.
     */
    public static function statusSelect(): Expression
    {
        return DB::raw(<<<'SQL'
            CASE
                WHEN em.last_operation = 'void' THEN 'void'
                WHEN em.effective_entry_id IS NULL THEN 'orphan'
                WHEN em.effective_captured_at > first.first_arrival THEN 'duplicate'
                WHEN em.last_correction_id IS NOT NULL THEN 'corrected'
                ELSE 'assigned'
            END AS status
            SQL);
    }

    private static function arrivalAggregate(Builder $from): Builder
    {
        return $from
            // constante sin binding: el motor incrusta este SQL como CTE con
            // sus propios bindings, así que nada de parámetros extra aquí.
            ->whereRaw('em.is_voided = 0')
            ->whereNotNull('em.effective_entry_id')
            ->selectRaw('em.effective_entry_id AS entry_id')
            ->selectRaw('MIN(em.effective_captured_at) AS first_arrival');
    }
}
