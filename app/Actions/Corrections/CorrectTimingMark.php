<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Jobs\Results\RecalculateEventResultsJob;
use App\Models\Entry;
use App\Models\Event;
use App\Models\TimingMark;
use App\Models\TimingMarkCorrection;
use App\Support\EffectiveMarks;
use App\Support\FormatClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ISSUE-135 (TQ-014, versión mínima F1) — Corrección de marca con las tres
 * operaciones del juez: (1) ajustar ±1 s/±0,1 s, (2) reasignar a otro
 * dorsal, (3) anular. La marca original NUNCA se toca (append-only, INV-01;
 * los triggers de la base lo exigen): la corrección es una fila auditada en
 * timing_mark_corrections cuyo before_after registra el estado efectivo
 * previo y el resultante, y el ESTADO EFECTIVO siempre lo decide la ÚLTIMA
 * corrección (misma derivación de EffectiveMarks que corre el motor).
 *
 * La reasignación sobre un dorsal que YA tiene llegada efectiva exige
 * confirmación explícita (confirm=true): el servidor la rechaza con el
 * error `confirm` que la pantalla convierte en tcConfirm y reenvío.
 */
final readonly class CorrectTimingMark
{
    private const OPERATIONS = ['adjust', 'reassign', 'void'];

    /**
     * @param  int  $deltaMs  desplazamiento de ESTA corrección (ms; botones ±1000/±100).
     * @param  int|null  $targetBib  dorsal destino (reassign): 1-3 dígitos, existente y distinto.
     * @param  bool  $confirm  confirma la reasignación cuando el destino ya tiene llegada.
     * @return array{operation: string, time: string, bib: int|null}
     */
    public function __invoke(
        Event $event,
        string $markUlid,
        string $operation,
        string $reason,
        int $deltaMs = 0,
        ?int $targetBib = null,
        bool $confirm = false,
    ): array {
        if (! in_array($operation, self::OPERATIONS, true)) {
            throw ValidationException::withMessages(['operation' => __('ui.events.correct_err_operation')]);
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('ui.events.correct_err_reason')]);
        }

        $mark = TimingMark::query()->where('event_id', $event->id)->where('ulid', $markUlid)->first();
        if ($mark === null) {
            throw ValidationException::withMessages(['mark' => __('ui.events.correct_err_missing')]);
        }

        // Estado efectivo VIGENTE con la derivación compartida (auditoría before).
        $current = DB::query()
            ->fromSub(EffectiveMarks::query($event->id), 'em')
            ->leftJoin('entries AS e', 'e.id', '=', 'em.effective_entry_id')
            ->where('em.ulid', $markUlid)
            ->first(['em.effective_entry_id', 'em.effective_captured_at', 'e.bib AS effective_bib']);

        $effectiveEntryId = $current?->effective_entry_id !== null ? (int) $current->effective_entry_id : null;
        $effectiveBib = $current?->effective_bib !== null ? (int) $current->effective_bib : null;
        $effectiveAt = CarbonImmutable::parse((string) $current?->effective_captured_at);
        $rawAt = CarbonImmutable::parse($mark->captured_at->format('Y-m-d H:i:s.v'));

        // Offset vigente (cuánto desplaza hoy la última corrección): compone
        // el offset TOTAL de la nueva corrección, que es el que derivará el SQL.
        $currentOffset = (int) $effectiveAt->getTimestampMs() - (int) $rawAt->getTimestampMs();

        $before = [
            'entry_id' => $effectiveEntryId,
            'bib' => $effectiveBib,
            'captured_at' => $effectiveAt->format('Y-m-d H:i:s.v'),
        ];

        [$after, $resultAt, $resultBib] = match ($operation) {
            'adjust' => $this->adjustAfter($rawAt, $currentOffset, $deltaMs),
            'reassign' => $this->reassignAfter($event, $effectiveAt, $effectiveEntryId, $targetBib, $confirm),
            default => [[], $effectiveAt, $effectiveBib],
        };

        $accountId = Auth::id();

        DB::transaction(function () use ($mark, $operation, $reason, $accountId, $before, $after): void {
            TimingMarkCorrection::query()->create([
                'ulid' => strtolower((string) str()->ulid()),
                'timing_mark_id' => $mark->id,
                'operation' => $operation,
                'reason' => $reason,
                'account_id' => $accountId === null ? null : (int) $accountId,
                'before_after' => ['before' => $before, 'after' => $after],
            ]);
        });

        // Recalculo tras el commit: la corrección nunca bloquea (INV-08).
        RecalculateEventResultsJob::dispatch($event->id)->afterCommit();

        return [
            'operation' => $operation,
            'time' => FormatClock::timeOfDay($resultAt, $event->timezone),
            'bib' => $resultBib,
        ];
    }

    /**
     * Ajuste: el delta de ESTA corrección (±1000/±100 ms) se suma al offset
     * vigente; el offset TOTAL desde la marca cruda es el que persiste en
     * after.delta_ms y el que derivará el SQL de marcas efectivas.
     *
     * @return array{0: array{captured_at: string, delta_ms: int}, 1: CarbonImmutable, 2: int|null}
     */
    private function adjustAfter(CarbonImmutable $rawAt, int $currentOffset, int $deltaMs): array
    {
        if ($deltaMs === 0) {
            throw ValidationException::withMessages(['delta' => __('ui.events.correct_err_delta_zero')]);
        }

        $totalOffset = $currentOffset + $deltaMs;
        $afterAt = $rawAt->addMilliseconds($totalOffset);

        return [
            ['captured_at' => $afterAt->format('Y-m-d H:i:s.v'), 'delta_ms' => $totalOffset],
            $afterAt,
            null, // el dorsal no cambia: el toast usa el efectivo previo
        ];
    }

    /**
     * Reasignación: el dorsal destino (1-3 dígitos) debe existir en el
     * evento, ser distinto del entry efectivo actual y, si ya tiene llegada
     * efectiva, confirmarse explícitamente — la marca movida será la
     * duplicada a menos que llegue antes que la existente.
     *
     * @return array{0: array{bib: int, entry_id: int, captured_at: string}, 1: CarbonImmutable, 2: int}
     */
    private function reassignAfter(Event $event, CarbonImmutable $effectiveAt, ?int $effectiveEntryId, ?int $targetBib, bool $confirm): array
    {
        if ($targetBib === null) {
            throw ValidationException::withMessages(['targetBib' => __('ui.events.correct_err_bib_missing')]);
        }

        if (! ctype_digit((string) $targetBib) || strlen((string) $targetBib) > 3 || $targetBib < 1) {
            throw ValidationException::withMessages(['targetBib' => __('ui.events.correct_err_bib_format')]);
        }

        $target = Entry::query()->forBib($event->id, $targetBib)->first();
        if ($target === null) {
            throw ValidationException::withMessages(['targetBib' => __('ui.events.correct_err_bib_unknown', ['bib' => $targetBib])]);
        }

        if ($effectiveEntryId !== null && $target->id === $effectiveEntryId) {
            throw ValidationException::withMessages(['targetBib' => __('ui.events.correct_err_bib_same', ['bib' => $targetBib])]);
        }

        $targetHasArrival = DB::query()
            ->fromSub(EffectiveMarks::query($event->id), 'em')
            ->where('em.is_voided', 0)
            ->where('em.effective_entry_id', $target->id)
            ->exists();

        if ($targetHasArrival && ! $confirm) {
            throw ValidationException::withMessages(['confirm' => __('ui.events.correct_confirm_target', ['bib' => $targetBib])]);
        }

        return [
            [
                'bib' => $targetBib,
                'entry_id' => (int) $target->id,
                'captured_at' => $effectiveAt->format('Y-m-d H:i:s.v'),
            ],
            $effectiveAt,
            $targetBib,
        ];
    }
}
