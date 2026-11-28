<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Crea una organización del directorio (ISSUE-110): la validación vive en el
 * servidor y es la única fuente de verdad — la UI solo la pinta bajo el campo.
 */
final readonly class CreateOrganization
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(array $data): Organization
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ])->validate();

        return Organization::query()->create([
            'region_id' => (int) $validated['region_id'],
            'name' => trim((string) $validated['name']),
        ]);
    }
}
