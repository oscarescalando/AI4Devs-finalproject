<?php

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
| ISSUE-108 (RF-68) — Login por sesiones en TALL: documento o correo +
| contraseña, remember, bloqueo 5/min por usuario+IP y solo cuentas activas.
| Sin pistas del sistema en la pantalla (spec/09-ui-estilo.md §1.7).
*/

new #[Layout('components.layouts.guest')] class extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->validate();

        $key = 'login:'.sha1(mb_strtolower($this->login).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->releaseLock($key);

            return;
        }

        $account = Account::query()
            ->where('email', $this->login)
            ->orWhereHas('person', fn (Builder $query): Builder => $query->where('document', $this->login))
            ->first();

        if ($account === null || ! $account->active || ! Hash::check($this->password, $account->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        Auth::guard('web')->login($account, $this->remember);
        session()->regenerate();

        redirect()->intended(route('panel.home'));
    }

    private function releaseLock(string $key): never
    {
        throw ValidationException::withMessages([
            'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
        ]);
    }
}

?>

<div>
    <h1 class="page-title">Acceso</h1>
    <p class="page-subtitle mb-5">Ingresa a tu cuenta para continuar.</p>

    @if (session('status'))
        <div class="alert alert-info mb-4" role="status">
            <x-ui.icon name="info" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="authenticate" class="flex flex-col gap-4">
        <div class="form-group">
            <label class="form-label" for="login">Documento o correo</label>
            <input
                id="login"
                type="text"
                class="form-control"
                wire:model="login"
                autocomplete="username"
                autofocus
                placeholder="tu@ejemplo.com"
                required
            >
            @error('login') <span class="form-error" role="alert">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <input
                id="password"
                type="password"
                class="form-control"
                wire:model="password"
                autocomplete="current-password"
                required
            >
            @error('password') <span class="form-error" role="alert">{{ $message }}</span> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm" style="color: var(--tc-text-2)">
            <input type="checkbox" class="form-check" wire:model="remember">
            <span>Recordarme</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="authenticate">
            <span wire:loading.remove wire:target="authenticate">Iniciar sesión</span>
            <span wire:loading class="spinner" wire:target="authenticate" aria-hidden="true"></span>
        </button>
    </form>
</div>
