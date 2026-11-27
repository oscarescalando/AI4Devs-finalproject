<?php

namespace App\Providers;

use App\Models\Account;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // RF-68: bloqueo por intentos — 5 por minuto por usuario+IP,
        // también cuando el usuario no existe (sin enumeración).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->input('login', '').'|'.$request->ip());
        });

        // spatie teams (RF-69): el contexto por defecto es el equipo 0
        // («plataforma») donde viven los roles globales (superadmin, admin…).
        // Los roles operativos por evento usan team_id = event_id y un
        // middleware de evento cambiará el contexto (ISSUE-115).
        $registrar = app(PermissionRegistrar::class);
        if ($registrar->getPermissionsTeamId() === null) {
            $registrar->setPermissionsTeamId(0);
        }

        // ISSUE-109 (TQ-008) — Convención de ESCRITURA del directorio: el
        // catálogo de permisos (ISSUE-159) solo define `directory.view` para
        // leer, y no se inventa un permiso nuevo ni se reutiliza
        // `accounts.manage` (ese gobierna cuentas de acceso, no el directorio).
        // Hasta que EP-25 aterrice `directory.manage` en el catálogo, escribir
        // en el directorio queda cerrado a los roles administradores (SRS §2:
        // admin administra personas/organizaciones/equipos). Cuando ese
        // permiso exista, el cuerpo de esta habilidad pasa a delegar en él.
        Gate::define(
            'directory.write',
            fn (Account $account): bool => $account->hasAnyRole(['admin', 'superadmin']),
        );
    }
}
