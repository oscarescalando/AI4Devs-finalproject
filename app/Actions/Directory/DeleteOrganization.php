<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * Elimina una organización del directorio (ISSUE-110): acción destructiva que
 * exige confirmación previa en la UI y rechaza la eliminación cuando la fila
 * está referenciada (equipos con restrictOnDelete) con un mensaje claro,
 * nunca un 500 silencioso.
 */
final readonly class DeleteOrganization
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $ulid): void
    {
        $organization = Organization::query()->where('ulid', $ulid)->firstOrFail();

        if ($organization->teams()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar «'.$organization->name.'»: tiene equipos asociados.',
            ]);
        }

        $organization->delete();
    }
}
