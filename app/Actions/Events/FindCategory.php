<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Category;

/**
 * Una categoría de un evento por su ULID público (TQ-026/ISSUE-160): carga
 * el formulario de edición. La consulta cruza por el evento del path para
 * que un ULID de otro evento nunca resuelva (ADR-015).
 */
final readonly class FindCategory
{
    public function __invoke(string $eventUlid, string $categoryUlid): ?Category
    {
        $category = Category::query()
            ->where('ulid', $categoryUlid)
            ->whereRelation('event', 'ulid', $eventUlid)
            ->first();

        return $category instanceof Category ? $category : null;
    }
}
