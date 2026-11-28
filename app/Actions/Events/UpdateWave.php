<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Race;
use App\Models\Wave;
use App\Support\EventTime;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Actualiza una oleada por su ULID (TQ-026/ISSUE-160): puede renombrarse,
 * reprogramarse (pared del evento → almacenamiento vía EventTime) y moverse
 * a otra carrera DEL MISMO evento — el select del editor solo ofrece esas.
 * Nombre único por carrera ignorándose a sí misma. La salida real
 * (actual_start_at) no se toca aquí: es del tablero de salidas (RF-15).
 */
final readonly class UpdateWave
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, string $waveUlid, array $data): Wave
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $wave = Wave::query()
            ->where('ulid', $waveUlid)
            ->whereRelation('race.event', 'ulid', $eventUlid)
            ->with('race')
            ->firstOrFail();

        $validated = Validator::make($data, [
            'race' => [
                'required',
                'string',
                Rule::exists('races', 'ulid')->where('event_id', $event->id),
            ],
            'name' => ['required', 'string', 'max:50'],
            'scheduled_start_at' => ['required', 'date'],
        ])->validate();

        $raceId = (int) Race::query()
            ->where('ulid', (string) $validated['race'])
            ->where('event_id', $event->id)
            ->value('id');

        $name = trim((string) $validated['name']);

        if (Wave::query()
            ->where('race_id', $raceId)
            ->where('name', $name)
            ->whereKeyNot($wave->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => __('ui.events.wave_err_name_taken'),
            ]);
        }

        $wave->update([
            'race_id' => $raceId,
            'name' => $name,
            'scheduled_start_at' => EventTime::parseLocal(
                $event->timezone,
                (string) $validated['scheduled_start_at'],
            ),
        ]);

        return $wave->refresh();
    }
}
