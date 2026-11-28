<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Organization;

/**
 * Una organización por su ULID público (ISSUE-110): el identificador entero
 * nunca sale del backend (ADR-015).
 */
final readonly class FindOrganization
{
    public function __invoke(string $ulid): ?Organization
    {
        $organization = Organization::query()
            ->where('ulid', $ulid)
            ->first();

        return $organization instanceof Organization ? $organization : null;
    }
}
