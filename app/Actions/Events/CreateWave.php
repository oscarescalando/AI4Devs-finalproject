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
 * Crea una oleada (TQ-026/ISSUE-160) sobre las columnas reales del esquema:
 * carrera (del MISMO evento, resuelta por su ULID público), name (50, único
 * por carrera) y scheduled_start_at datetime(3). El editor exige nombre y
 * hora — el esquema los mantiene nullable para legados y para el fallback
 * «Oleada» del tablero, pero una oleada nace del editor ya programada.
 *
 * La hora llega como pared del evento (input datetime-local, admite ms) y se
 * guarda en la zona de la app vía EventTime (INV-10).
 */
final readonly class CreateWave
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, array $data): Wave
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

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

        if (Wave::query()->where('race_id', $raceId)->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => __('ui.events.wave_err_name_taken'),
            ]);
        }

        return Wave::query()->create([
            'race_id' => $raceId,
            'name' => $name,
            'scheduled_start_at' => EventTime::parseLocal(
                $event->timezone,
                (string) $validated['scheduled_start_at'],
            ),
        ]);
    }
}
