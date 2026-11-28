<?php

declare(strict_types=1);

namespace App\Actions\Directory\Team;

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Actualiza un equipo del directorio (ISSUE-161, TQ-028): resuelve por ULID
 * público y valida en el servidor antes de escribir.
 */
final readonly class UpdateTeam
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $ulid, array $data): Team
    {
        $team = Team::query()->where('ulid', $ulid)->firstOrFail();

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'organization_ulid' => ['required', 'string', 'exists:organizations,ulid'],
        ])->validate();

        $organization = Organization::query()->where('ulid', $validated['organization_ulid'])->firstOrFail();

        $team->update([
            'organization_id' => $organization->id,
            'name' => trim((string) $validated['name']),
        ]);

        return $team->refresh();
    }
}
