<?php

declare(strict_types=1);

namespace App\Actions\Directory\Team;

use App\Models\Team;

/**
 * Un equipo por su ULID público (ISSUE-161): el identificador entero nunca
 * sale del backend (ADR-015).
 */
final readonly class FindTeam
{
    public function __invoke(string $ulid): ?Team
    {
        $team = Team::query()
            ->with('organization')
            ->where('ulid', $ulid)
            ->first();

        return $team instanceof Team ? $team : null;
    }
}
