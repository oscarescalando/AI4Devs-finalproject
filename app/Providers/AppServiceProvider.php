<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
