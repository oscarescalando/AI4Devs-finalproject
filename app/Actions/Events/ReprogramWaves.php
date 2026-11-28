<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Wave;
use App\Support\EventTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * ISSUE-050 (TQ-026) — Reprogramación en bloque de las oleadas de UNA
 * carrera: hora inicial + intervalo en minutos → TODAS las oleadas de esa
 * carrera se recalculan (inicial, inicial+intervalo, inicial+2·intervalo…)
 * en su orden vigente del tablero (scheduled_start_at, empate por id; las
 * sin hora quedan primero) dentro de UNA transacción con fila bloqueada:
 * o queda el horario completo o no cambia nada — nunca medias
 * reprogramaciones visibles. Devuelve el número de oleadas reprogramadas.
 */
final readonly class ReprogramWaves
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, string $raceUlid, string $startAt, int $intervalMinutes): int
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $validated = Validator::make([
            'start_at' => $startAt,
            'interval' => $intervalMinutes,
        ], [
            'start_at' => ['required', 'date'],
            'interval' => ['required', 'integer', 'min:1', 'max:1440'],
        ])->validate();

        return (int) DB::transaction(function () use ($event, $raceUlid, $validated): int {
            $race = $event->races()->where('ulid', $raceUlid)->lockForUpdate()->first();

            if ($race === null) {
                throw ValidationException::withMessages([
                    'race' => __('ui.events.wave_err_missing_race'),
                ]);
            }

            /** @var Collection<int, Wave> $waves */
            $waves = $race->waves()
                ->orderBy('scheduled_start_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($waves->isEmpty()) {
                throw ValidationException::withMessages([
                    'race' => __('ui.events.wave_reprogram_empty'),
                ]);
            }

            $start = EventTime::parseLocal($event->timezone, (string) $validated['start_at']);
            $interval = (int) $validated['interval'];

            foreach ($waves as $index => $wave) {
                $wave->update(['scheduled_start_at' => $start->addMinutes($index * $interval)]);
            }

            return $waves->count();
        });
    }
}
