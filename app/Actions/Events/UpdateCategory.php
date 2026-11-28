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
 * Actualiza una categoría del evento por su ULID (TQ-026/ISSUE-160): mismas
 * reglas y coherencia de rangos que CreateCategory (name único por evento
 * ignorándose a sí misma; solapamiento solo dentro del mismo token de
 * género, excluyéndose a sí misma). Resuelve por el evento del path: un
 * ULID de otro evento no resuelve (ADR-015).
 */
final readonly class UpdateCategory
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, string $categoryUlid, array $data): Category
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $category = Category::query()
            ->where('ulid', $categoryUlid)
            ->whereRelation('event', 'ulid', $eventUlid)
            ->firstOrFail();

        $validated = Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'name')
                    ->where('event_id', $event->id)
                    ->ignore($category->id),
            ],
            'for_teams' => ['sometimes', 'boolean'],
            'min_age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:1', 'max:120'],
        ])->validate();

        $min = $this->asAge($validated['min_age'] ?? null);
        $max = $this->asAge($validated['max_age'] ?? null);
        $name = trim((string) $validated['name']);

        if ($min !== null && $max !== null && $max < $min) {
            throw ValidationException::withMessages([
                'max_age' => __('ui.events.cat_err_order'),
            ]);
        }

        $token = CategoryRange::genderToken($name);

        if ($token !== null && ($min !== null || $max !== null)) {
            /** @var Collection<int, Category> $others */
            $others = Category::query()
                ->where('event_id', $event->id)
                ->where('name', 'like', $token.' %')
                ->whereKeyNot($category->id)
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

        $category->update([
            'name' => $name,
            'for_teams' => (bool) ($validated['for_teams'] ?? false),
            'min_age' => $min,
            'max_age' => $max,
        ]);

        return $category->refresh();
    }

    private function asAge(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
