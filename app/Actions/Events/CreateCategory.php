<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Category;
use App\Models\Event;
use App\Support\CategoryRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea una categoría del evento (TQ-026/ISSUE-160) con las columnas reales
 * del esquema: name (50, única por evento), for_teams, min_age/max_age
 * (1–120, nullable). Coherencia mínima de rangos: max >= min y, como el
 * esquema no tiene columna de género (viaja en el prefijo del nombre,
 * ver CategoryRange), sin solapamiento entre categorías del MISMO token
 * «M …»/«F …». La validación vive en el servidor (única fuente de verdad).
 */
final readonly class CreateCategory
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, array $data): Category
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $validated = Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'name')->where('event_id', $event->id),
            ],
            'for_teams' => ['sometimes', 'boolean'],
            'min_age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:1', 'max:120'],
        ])->validate();

        $min = $this->asAge($validated['min_age'] ?? null);
        $max = $this->asAge($validated['max_age'] ?? null);
        $name = trim((string) $validated['name']);

        $this->assertOrderedRange($min, $max);
        $this->assertNoOverlap($event->id, $name, $min, $max, null);

        return Category::query()->create([
            'event_id' => $event->id,
            'name' => $name,
            'for_teams' => (bool) ($validated['for_teams'] ?? false),
            'min_age' => $min,
            'max_age' => $max,
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function assertOrderedRange(?int $min, ?int $max): void
    {
        if ($min !== null && $max !== null && $max < $min) {
            throw ValidationException::withMessages([
                'max_age' => __('ui.events.cat_err_order'),
            ]);
        }
    }

    /**
     * Sin solapamiento contra las categorías del mismo token de género
     * (CategoryRange documenta por qué las demás no participan).
     *
     * @throws ValidationException
     */
    private function assertNoOverlap(int $eventId, string $name, ?int $min, ?int $max, ?int $ignoreId): void
    {
        $token = CategoryRange::genderToken($name);

        if ($token === null || ($min === null && $max === null)) {
            return;
        }

        /** @var Collection<int, Category> $others */
        $others = Category::query()
            ->where('event_id', $eventId)
            ->where('name', 'like', $token.' %')
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['name', 'min_age', 'max_age']);

        foreach ($others as $other) {
            if (CategoryRange::overlaps(
                ['min_age' => $min, 'max_age' => $max],
                ['min_age' => $other->min_age, 'max_age' => $other->max_age],
            )) {
                throw ValidationException::withMessages([
                    'min_age' => __('ui.events.cat_err_overlap', ['name' => $other->name]),
                ]);
            }
        }
    }

    private function asAge(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
