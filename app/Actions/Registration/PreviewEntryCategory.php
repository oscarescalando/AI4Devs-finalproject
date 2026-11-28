<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Category;
use App\Models\Event;
use App\Models\Person;
use App\Support\CategoryMatch;
use App\Support\MaskDocument;
use Illuminate\Support\Collection;

/**
 * Previsualización en vivo de la categoría del alta (ISSUE-162/TQ-030):
 * aplica EXACTAMENTE la misma regla que CreateEntry (CategoryMatch sobre
 * las categorías del evento, edad al día del evento + género de la persona)
 * para que la etiqueta que ve el operador antes de guardar sea la que se
 * persiste. Devuelve también el nombre público y el documento enmascarado
 * de la persona para el resumen del picker (INV-06/RNF-13).
 */
final readonly class PreviewEntryCategory
{
    /**
     * @return array{name: string, document: string, age: int, gender: string, category: string|null}|null
     */
    public function __invoke(string $eventUlid, string $personUlid): ?array
    {
        $event = Event::query()->where('ulid', $eventUlid)->first();
        $person = Person::query()->where('ulid', $personUlid)->first();

        if ($event === null || $person === null) {
            return null;
        }

        $age = (int) $person->birthdate->diff($event->date)->y;
        $category = CategoryMatch::find($this->categories($event), $person->gender, $age);

        return [
            'name' => $person->publicName(),
            'document' => MaskDocument::mask($person->document),
            'age' => $age,
            'gender' => $person->gender,
            'category' => $category?->name,
        ];
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(Event $event): Collection
    {
        return $event->categories()->orderBy('min_age')->orderBy('id')->get();
    }
}
