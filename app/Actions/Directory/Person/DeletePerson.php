<?php

declare(strict_types=1);

namespace App\Actions\Directory\Person;

use App\Models\Person;
use Illuminate\Validation\ValidationException;

/**
 * Elimina una persona del directorio (ISSUE-161, TQ-028): acción destructiva
 * que exige confirmación previa en la UI y se bloquea cuando la fila está
 * referenciada — con inscripciones el FK restrict del servidor la protege,
 * y con cuenta de acceso se bloquea aquí aunque la BD haría cascade: borrar
 * la cuenta de acceso nunca puede ser un efecto colateral silencioso
 * (persona ≠ cuenta, ADR-015). Mensaje de negocio claro, nunca un 500.
 */
final readonly class DeletePerson
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $ulid): void
    {
        $person = Person::query()->where('ulid', $ulid)->firstOrFail();
        $name = trim($person->first_name.' '.$person->last_name);

        if ($person->entries()->exists()) {
            throw ValidationException::withMessages([
                'document' => 'No se puede eliminar a «'.$name.'»: tiene inscripciones asociadas.',
            ]);
        }

        if ($person->account()->exists()) {
            throw ValidationException::withMessages([
                'document' => 'No se puede eliminar a «'.$name.'»: tiene una cuenta de acceso asociada.',
            ]);
        }

        $person->delete();
    }
}
