<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Category;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Categorías del evento para el editor del panel (TQ-026/ISSUE-160): columnas
 * reales del esquema — name, for_teams, min_age/max_age (nullable) — más los
 * inscritos como TOTAL (withCount sobre todos sus entries). El rango llega
 * ya formateado («20–29», «18+», «—») para que la vista no decore; el
 * identificador público es el ULID (ADR-015).
 */
final readonly class ListCategories
{
    /**
     * @return array{name: string, rows: list<array{
     *     ulid: string,
     *     name: string,
     *     for_teams: bool,
     *     range: string,
     *     entries_total: int,
     * }>}
     */
    public function __invoke(string $eventUlid): array
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        /** @var Collection<int, Category> $categories */
        $categories = $event->categories()
            ->withCount('entries')
            ->orderBy('for_teams')
            ->orderBy('min_age')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($categories as $category) {
            $rows[] = [
                'ulid' => $category->ulid,
                'name' => $category->name,
                'for_teams' => $category->for_teams,
                'range' => $this->range($category->min_age, $category->max_age),
                'entries_total' => (int) $category->entries_count,
            ];
        }

        return ['name' => $event->name, 'rows' => $rows];
    }

    /**
     * Rango legible: cerrado «20–29», abierto por arriba «18+», abierto por
     * abajo «≤49» y sin rango «—». Solo formato: sin palabras que traducir.
     */
    private function range(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return '—';
        }

        if ($max === null) {
            return $min.'+';
        }

        return $min !== null ? $min.'–'.$max : '≤'.$max;
    }
}
