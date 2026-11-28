<?php

declare(strict_types=1);

namespace App\Actions\Start;

use App\Models\Event;
use App\Models\Wave;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * ISSUE-133 (TQ-013, versión mínima F1) — Deshace la salida real de una
 * oleada: solo si tiene hora real y en orden inverso (si una oleada
 * posterior sigue iniciada, rechaza — las salidas dadas forman un prefijo
 * del tablero y hay que deshacer desde la última). Anula `actual_start_at`
 * sin tocar la programada; la oleada vuelve a Pendiente y puede marcarse
 * de nuevo. La auditoría del deshacer (ISSUE-045) llega con F2: hoy el
 * cambio se registra vía `updated_at` (diferido documentado en TQ-013).
 */
final readonly class UndoWaveStart
{
    /**
     * @return array{ulid: string, name: string}
     */
    public function __invoke(Event $event, string $waveUlid): array
    {
        $ordered = $this->orderedWaves($event);

        $position = $ordered->search(fn (Wave $candidate): bool => $candidate->ulid === $waveUlid);

        if ($position === false) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.start_err_missing'),
            ]);
        }

        /** @var Wave $wave */
        $wave = $ordered->get($position);

        if ($wave->actual_start_at === null) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.start_err_not_started', ['name' => $this->label($wave)]),
            ]);
        }

        /** @var Wave|null $laterStarted */
        $laterStarted = $ordered->slice($position + 1)
            ->first(fn (Wave $candidate): bool => $candidate->actual_start_at !== null);

        if ($laterStarted !== null) {
            throw ValidationException::withMessages([
                'wave' => __('ui.events.start_err_later', ['later' => $this->label($laterStarted)]),
            ]);
        }

        $wave->fill(['actual_start_at' => null])->save();

        return [
            'ulid' => $wave->ulid,
            'name' => $this->label($wave),
        ];
    }

    /**
     * Oleadas del evento en el orden del tablero (ISSUE-132).
     *
     * @return Collection<int, Wave>
     */
    private function orderedWaves(Event $event): Collection
    {
        return Wave::query()
            ->whereRelation('race', 'event_id', $event->id)
            ->with('race')
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->get();
    }

    private function label(Wave $wave): string
    {
        return $wave->name ?? __('ui.events.start_wave_fallback');
    }
}
