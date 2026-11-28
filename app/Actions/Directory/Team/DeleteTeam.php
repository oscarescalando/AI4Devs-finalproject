<?php

declare(strict_types=1);

namespace App\Actions\Directory\Team;

use App\Models\Team;
use Illuminate\Validation\ValidationException;

/**
 * Elimina un equipo del directorio (ISSUE-161, TQ-028): acción destructiva
 * que exige confirmación previa en la UI y se bloquea cuando el equipo tiene
 * inscripciones (FK restrict del servidor) con un mensaje de negocio claro,
 * nunca un 500 silencioso.
 */
final readonly class DeleteTeam
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $ulid): void
    {
        $team = Team::query()->where('ulid', $ulid)->firstOrFail();

        if ($team->entries()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar «'.$team->name.'»: tiene inscripciones asociadas.',
            ]);
        }

        $team->delete();
    }
}
