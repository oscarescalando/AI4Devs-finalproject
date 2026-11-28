<?php

declare(strict_types=1);

namespace App\Actions\Directory\Team;

use App\Models\Organization;

/**
 * Organizaciones para el selector del formulario y el filtro de equipos
 * (ISSUE-161, TQ-028): clave pública ULID (ADR-015). El esquema del
 * directorio no tiene columna de estado (ADR-015): hoy «activas» = todas;
 * si EP-25 aterriza un estado de organización, el filtro vive aquí y
 * nadie más cambia.
 */
final readonly class ListOrganizationOptions
{
    /** @return list<array{ulid: string, name: string}> */
    public function __invoke(): array
    {
        $organizations = Organization::query()->orderBy('name')->get(['ulid', 'name']);

        return array_values(array_map(
            fn (Organization $organization): array => ['ulid' => $organization->ulid, 'name' => $organization->name],
            $organizations->all(),
        ));
    }
}
