<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Race;
use App\Support\Ui\EventStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Actualiza un evento y sincroniza sus carreras en la MISMA transacción
 * (TQ-025/ISSUE-160): upsert por id (nombre), alta de las nuevas y baja de
 * las retiradas SOLO si no tienen inscripciones (ni oleadas: sus FK son
 * restrict) — si tienen, error de negocio claro y ningún cambio persistido.
 * Resuelve por ULID público (ADR-015) y valida en el servidor.
 */
final readonly class UpdateEvent
{
    private const RACE_RULES = [
        'races' => ['nullable', 'array'],
        'races.*.name' => ['required', 'string', 'max:50', 'distinct'],
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $ulid, array $data): Event
    {
        $event = Event::query()
            ->with('races')
            ->where('ulid', $ulid)
            ->firstOrFail();

        $validated = Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('events', 'name')->ignore($event->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('events', 'slug')->ignore($event->id),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(EventStatus::STATUSES)],
            ...self::RACE_RULES,
        ])->validate();

        return DB::transaction(function () use ($event, $validated): Event {
            $event->update([
                'name' => trim((string) $validated['name']),
                'slug' => trim((string) $validated['slug']),
                'date' => $validated['date'],
                'status' => (string) $validated['status'],
            ]);

            $this->syncRaces($event, $this->raceRows($validated));

            return $event->refresh();
        });
    }

    /**
     * Sincroniza las carreras del formulario contra la base: las filas con id
     * se renombran (si el id pertenece al evento), las nuevas se crean y las
     * ausentes se eliminan solo cuando nadie las referencia.
     *
     * @param  list<array{id: string|null, name: string}>  $rows
     */
    private function syncRaces(Event $event, array $rows): void
    {
        /** @var Collection<int, Race> $originals */
        $originals = $event->races->keyBy('id');

        $keptIds = [];
        foreach ($rows as $row) {
            $name = trim((string) $row['name']);
            $rawId = $row['id'];
            $id = ($rawId !== null && $rawId !== '') ? (int) $rawId : null;

            if ($id !== null && $originals->has($id)) {
                $originals->get($id)?->update(['name' => $name]);
                $keptIds[] = $id;

                continue;
            }

            $event->races()->create(['name' => $name]);
        }

        foreach ($originals as $race) {
            if (in_array($race->id, $keptIds, true)) {
                continue;
            }

            if ($race->entries()->exists()) {
                throw ValidationException::withMessages([
                    'races' => 'La carrera «'.$race->name.'» tiene inscripciones: no se puede retirar del evento.',
                ]);
            }

            if ($race->waves()->exists()) {
                throw ValidationException::withMessages([
                    'races' => 'La carrera «'.$race->name.'» tiene oleadas configuradas: retira sus oleadas primero.',
                ]);
            }

            $race->delete();
        }
    }

    /**
     * Filas de carrera del payload validado, sin filas que no sean arrays.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array{id: string|null, name: string}>
     */
    private function raceRows(array $validated): array
    {
        $rows = [];
        foreach (array_is_list($validated['races'] ?? null) ? $validated['races'] : [] as $race) {
            if (is_array($race)) {
                $rows[] = [
                    'id' => is_scalar($race['id'] ?? null) ? (string) $race['id'] : null,
                    'name' => (string) ($race['name'] ?? ''),
                ];
            }
        }

        return $rows;
    }
}
