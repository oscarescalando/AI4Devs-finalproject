<?php

use App\Actions\Auth\UpdateOwnPassword;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-113 (RF-71, TQ-005) — Perfil propio: datos no sensibles en solo
| lectura (la cuenta NUNCA se autogestiona roles ni estado activo) y cambio
| de contraseña exigiendo la actual; al cambiarla la Action cierra las
| demás sesiones. Tras guardar los tres campos quedan vacíos: nunca se
| rellenan con el hash. Solo componentes del kit x-ui.* (spec/09-ui-estilo).
*/

new #[Layout('components.layouts.panel')] #[Title('Perfil')] class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        /** @var Account $account */
        $account = Auth::user();

        try {
            app(UpdateOwnPassword::class)($account, [
                'current_password' => $this->current_password,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
        } catch (ValidationException $exception) {
            // El error del servidor viaja al campo (ISSUE-110): el kit lo
            // pinta bajo el campo correspondiente.
            throw ValidationException::withMessages($exception->errors());
        }

        // Campos vacíos tras guardar; la validación anterior se limpia para
        // que no queden errores huérfanos en pantalla.
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->resetValidation();

        $this->dispatch('toast', type: 'success', message: 'Contraseña actualizada');
    }
};

?>

@php($account = auth()->user())

<div class="flex flex-col gap-5">
    <x-ui.page-header
        title="Perfil"
        subtitle="Los datos de tu cuenta de acceso y el cambio de contraseña."
    />

    <div class="grid items-start gap-5 lg:grid-cols-2">
        {{-- Cuenta: SOLO lectura — sin selector de rol ni control de
             activación (ISSUE-113: eso lo gestiona quien tenga
             accounts.manage, no la propia cuenta). --}}
        <x-ui.card title="Cuenta" subtitle="Datos identificativos de tu acceso.">
            <div class="flex flex-col gap-4">
                <x-ui.form-group label="Nombre" for="profile-name">
                    <input
                        id="profile-name"
                        type="text"
                        class="form-control"
                        value="{{ $account?->person?->first_name }} {{ $account?->person?->last_name }}"
                        disabled
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    label="Correo electrónico"
                    for="profile-email"
                    help="El correo de acceso no se cambia desde el perfil."
                >
                    <input
                        id="profile-email"
                        type="email"
                        class="form-control"
                        value="{{ $account?->email }}"
                        disabled
                    >
                </x-ui.form-group>

                <x-ui.form-group label="Roles" for="profile-roles" help="Asignados por la administración de la plataforma.">
                    <span id="profile-roles" class="flex flex-wrap gap-1.5">
                        @forelse ($account?->getRoleNames() ?? [] as $role)
                            <x-ui.badge variant="neutral">{{ config('tc-ui.roles.'.$role, $role) }}</x-ui.badge>
                        @empty
                            <x-ui.badge variant="neutral">Sin rol asignado</x-ui.badge>
                        @endforelse
                    </span>
                </x-ui.form-group>
            </div>
        </x-ui.card>

        {{-- Contraseña: exige la actual; las demás sesiones se cierran al
             cambiarla (ISSUE-113). --}}
        <x-ui.card title="Contraseña" subtitle="Al cambiarla se cerrarán tus demás sesiones.">
            <form wire:submit="updatePassword" class="flex flex-col gap-4">
                <x-ui.form-group
                    label="Contraseña actual"
                    for="current_password"
                    required
                    :error="$errors->first('current_password')"
                >
                    <input
                        id="current_password"
                        type="password"
                        class="form-control"
                        autocomplete="current-password"
                        wire:model="current_password"
                        required
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    label="Contraseña nueva"
                    for="password"
                    required
                    help="Mínimo 8 caracteres (política mínima vigente)."
                    :error="$errors->first('password')"
                >
                    <input
                        id="password"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        wire:model="password"
                        required
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    label="Confirmar contraseña"
                    for="password_confirmation"
                    required
                    :error="$errors->first('password_confirmation')"
                >
                    <input
                        id="password_confirmation"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        wire:model="password_confirmation"
                        required
                    >
                </x-ui.form-group>

                <div class="flex justify-end">
                    <x-ui.button
                        variant="primary"
                        icon="key-round"
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="updatePassword"
                    >
                        <span wire:loading.remove wire:target="updatePassword">Actualizar contraseña</span>
                        <span wire:loading wire:target="updatePassword" class="spinner" aria-hidden="true"></span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
