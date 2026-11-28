<?php

declare(strict_types=1);

namespace App\Actions\Directory\Team;

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Crea un equipo del directorio (ISSUE-161, TQ-028): nombre + organización,
 * validados en el servidor. La organización viaja por su ULID público
 * (ADR-015) y el id entero se resuelve aquí, dentro del backend.
 */
final readonly class CreateTeam
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(array $data): Team
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'organization_ulid' => ['required', 'string', 'exists:organizations,ulid'],
        ])->validate();

        $organization = Organization::query()->where('ulid', $validated['organization_ulid'])->firstOrFail();

        return Team::query()->create([
            'organization_id' => $organization->id,
            'name' => trim((string) $validated['name']),
        ]);
    }
}
