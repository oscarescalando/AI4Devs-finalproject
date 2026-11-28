<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Crea un evento con sus carreras en la MISMA transacción (TQ-025/ISSUE-160):
 * el evento nace con el estado inicial del esquema (events.status DEFAULT
 * 'live') — el selector de estado solo existe al editar. La validación vive
 * en el servidor y es la única fuente de verdad; la UI la pinta bajo el
 * campo. Sin borrado: los eventos se cierran, no se eliminan (DEFER de
 * TQ-025: con inscripciones rompe las FK restrict).
 */
final readonly class CreateEvent
{
    /**
     * Carreras: el esquema real (2026_11_24_000002) solo tiene `name` (50) —
     * la distancia y el tipo viajan dentro del nombre («21K», «Trail 12K»,
     * «OCR 8K»), sin columnas propias.
     */
    private const RACE_RULES = [
        'races' => ['nullable', 'array'],
        'races.*.name' => ['required', 'string', 'max:50', 'distinct'],
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(array $data): Event
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150', 'unique:events,name'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:events,slug',
            ],
            'date' => ['required', 'date'],
            'timezone' => ['sometimes', 'string', 'max:64', 'timezone'],
            ...self::RACE_RULES,
        ])->validate();

        return DB::transaction(function () use ($validated): Event {
            $event = Event::query()->create([
                'name' => trim((string) $validated['name']),
                'slug' => trim((string) $validated['slug']),
                'date' => $validated['date'],
                // Sin estado en el alta: aplica el default del esquema.
                'timezone' => $validated['timezone'] ?? 'America/Caracas',
            ]);

            foreach ($this->raceNames($validated) as $raceName) {
                $event->races()->create(['name' => $raceName]);
            }

            return $event;
        });
    }

    /**
     * Nombres de carrera normalizados (trim) y sin filas vacías.
     *
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function raceNames(array $validated): array
    {
        $names = [];
        foreach (array_is_list($validated['races'] ?? null) ? $validated['races'] : [] as $race) {
            if (is_array($race)) {
                $names[] = trim((string) ($race['name'] ?? ''));
            }
        }

        return $names;
    }
}
