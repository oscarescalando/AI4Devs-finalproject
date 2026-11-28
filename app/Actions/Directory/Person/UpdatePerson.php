<?php

declare(strict_types=1);

namespace App\Actions\Directory\Person;

use App\Models\Person;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Actualiza una persona del directorio (ISSUE-161, TQ-028): resuelve por
 * ULID público, valida en el servidor antes de escribir y el documento
 * sigue siendo único global — ignora la propia fila al comprobarlo.
 */
final readonly class UpdatePerson
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(string $ulid, array $data): Person
    {
        $person = Person::query()->where('ulid', $ulid)->firstOrFail();

        $validated = Validator::make(
            ['document' => mb_strtoupper(trim((string) ($data['document'] ?? '')))] + $data,
            [
                'document' => [
                    'required', 'string', 'max:30',
                    'regex:/^[VE]-[0-9]{6,8}$/',
                    Rule::unique('persons', 'document')->ignore($person->getKey()),
                ],
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'birthdate' => [
                    'required', 'date', 'before:today',
                    'before_or_equal:'.now()->subYears(5)->toDateString(),
                    'after_or_equal:'.now()->subYears(120)->toDateString(),
                ],
                'gender' => ['required', 'string', 'in:M,F,X'],
            ],
            self::messages(),
        )->validate();

        $person->update([
            'document' => $validated['document'],
            'first_name' => trim((string) $validated['first_name']),
            'last_name' => trim((string) $validated['last_name']),
            'birthdate' => $validated['birthdate'],
            'gender' => $validated['gender'],
        ]);

        return $person->refresh();
    }

    /**
     * Mensajes de negocio: mismos textos que la creación — el formato del
     * documento y el rango de edad se explican solos.
     *
     * @return array<string, string>
     */
    private static function messages(): array
    {
        return [
            'document.regex' => 'El documento debe tener formato V- o E- seguido de 6 a 8 dígitos.',
            'document.unique' => 'Ya existe una persona registrada con ese documento.',
            'birthdate.before' => 'La fecha de nacimiento debe ser una fecha pasada.',
            'birthdate.before_or_equal' => 'La fecha de nacimiento debe corresponder a una edad entre 5 y 120 años.',
            'birthdate.after_or_equal' => 'La fecha de nacimiento debe corresponder a una edad entre 5 y 120 años.',
        ];
    }
}
