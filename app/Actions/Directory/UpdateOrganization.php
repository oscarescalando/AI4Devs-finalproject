<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Actualiza una organización del directorio (ISSUE-110): resuelve por ULID
 * público y valida en el servidor antes de escribir.
 */
final readonly class UpdateOrganization
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $ulid, array $data): Organization
    {
        $organization = Organization::query()->where('ulid', $ulid)->firstOrFail();

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ])->validate();

        $organization->update([
            'region_id' => (int) $validated['region_id'],
            'name' => trim((string) $validated['name']),
        ]);

        return $organization->refresh();
    }
}
