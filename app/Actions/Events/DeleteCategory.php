<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Elimina una categoría del evento por su ULID (TQ-026/ISSUE-160): bloqueada
 * cuando tiene inscripciones — entries.category_id es FK restrict y una
 * categoría en uso no se destruye, se reorganiza el evento antes. El error
 * es de negocio, con nombre legible, y la pantalla lo pinta como toast.
 */
final readonly class DeleteCategory
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, string $categoryUlid): void
    {
        $category = Category::query()
            ->where('ulid', $categoryUlid)
            ->whereRelation('event', 'ulid', $eventUlid)
            ->firstOrFail();

        if ($category->entries()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('ui.events.cat_err_has_entries', ['name' => $category->name]),
            ]);
        }

        $category->delete();
    }
}
