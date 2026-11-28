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

        $this->dispatch('toast', type: 'success', message: __('ui.profile.password_updated'));
    }
};

?>

@php($account = auth()->user())

<div class="flex flex-col gap-5">
    <x-ui.page-header
        :title="__('ui.profile.title')"
        :subtitle="__('ui.profile.subtitle')"
    />

    <div class="grid items-start gap-5 lg:grid-cols-2">
        {{-- Cuenta: SOLO lectura — sin selector de rol ni control de
             activación (ISSUE-113: eso lo gestiona quien tenga
             accounts.manage, no la propia cuenta). --}}
        <x-ui.card :title="__('ui.profile.account')" :subtitle="__('ui.profile.account_subtitle')">
            <div class="flex flex-col gap-4">
                <x-ui.form-group :label="__('ui.profile.name')" for="profile-name">
                    <input
                        id="profile-name"
                        type="text"
                        class="form-control"
                        value="{{ $account?->person?->first_name }} {{ $account?->person?->last_name }}"
                        disabled
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.profile.email')"
                    for="profile-email"
                    :help="__('ui.profile.email_help')"
                >
                    <input
                        id="profile-email"
                        type="email"
                        class="form-control"
                        value="{{ $account?->email }}"
                        disabled
                    >
                </x-ui.form-group>

                <x-ui.form-group :label="__('ui.profile.roles')" for="profile-roles" :help="__('ui.profile.roles_help')">
                    <span id="profile-roles" class="flex flex-wrap gap-1.5">
                        @forelse ($account?->getRoleNames() ?? [] as $role)
                            <x-ui.badge variant="neutral">{{ __(config('tc-ui.roles.'.$role, $role)) }}</x-ui.badge>
                        @empty
                            <x-ui.badge variant="neutral">{{ __('ui.profile.no_role') }}</x-ui.badge>
                        @endforelse
                    </span>
                </x-ui.form-group>
            </div>
        </x-ui.card>

        {{-- Contraseña: exige la actual; las demás sesiones se cierran al
             cambiarla (ISSUE-113). --}}
        <x-ui.card :title="__('ui.profile.password')" :subtitle="__('ui.profile.password_subtitle')">
            <form wire:submit="updatePassword" class="flex flex-col gap-4">
                <x-ui.form-group
                    :label="__('ui.profile.current_password')"
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
                    :label="__('ui.profile.new_password')"
                    for="password"
                    required
                    :help="__('ui.profile.new_password_help')"
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
                    :label="__('ui.profile.confirm_password')"
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
                        <span wire:loading.remove wire:target="updatePassword">{{ __('ui.profile.update') }}</span>
                        <span wire:loading wire:target="updatePassword" class="spinner" aria-hidden="true"></span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
