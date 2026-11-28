<?php

declare(strict_types=1);

namespace App\Actions\Directory\Person;

use App\Models\Person;

/**
 * Una persona por su ULID público (ISSUE-161): el identificador entero nunca
 * sale del backend (ADR-015).
 */
final readonly class FindPerson
{
    public function __invoke(string $ulid): ?Person
    {
        $person = Person::query()
            ->where('ulid', $ulid)
            ->first();

        return $person instanceof Person ? $person : null;
    }
}
