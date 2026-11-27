<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de la propia contraseña (ISSUE-113, RF-71): exige la contraseña
 * actual validada en el servidor, confirma la nueva y cierra las demás
 * sesiones de la cuenta. La propia cuenta no se autogestiona roles ni
 * estado: aquí solo se toca `password`.
 */
final readonly class UpdateOwnPassword
{
    /**
     * @param  array<string, mixed>  $credentials
     *
     * @throws ValidationException
     */
    public function __invoke(Account $account, array $credentials): void
    {
        $validator = Validator::make($credentials, [
            'current_password' => ['required', 'string'],
            // Política mínima mientras la spec no dicte más (RF-71 solo exige
            // actual + confirmación): 8 caracteres y confirmación.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Nombres legibles para los mensajes en español (spec/09-ui-estilo §10).
        $validator->setAttributeNames([
            'current_password' => 'contraseña actual',
            'password' => 'contraseña nueva',
            'password_confirmation' => 'confirmación de contraseña',
        ]);

        $validated = $validator->validate();

        if (! Hash::check($validated['current_password'], $account->password)) {
            // Mensaje genérico: sin pistas sobre la fortaleza de la actual.
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        // El cast `hashed` de Account cifra al asignar: se guarda el valor
        // plano, jamás un hash del hash.
        $account->forceFill(['password' => $validated['password']])->save();

        // Invalida las demás sesiones (ISSUE-113): no hay modelo de sesión,
        // se depura la tabla directamente — permitido en Actions, jamás en
        // controladores (ADR-012). La sesión actual sobrevive.
        DB::table('sessions')
            ->where('user_id', $account->id)
            ->where('id', '!=', session()->getId())
            ->delete();
    }
}
