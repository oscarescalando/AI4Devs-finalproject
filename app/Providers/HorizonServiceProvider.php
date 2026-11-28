<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Account;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#alerts');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // ISSUE-144 (TQ-017): el dashboard de colas solo lo ve superadmin
        // (matriz de RolesPermissionsSeeder). En entorno local Horizon
        // autoriza por defecto (comportamiento del paquete); la puerta
        // aplica en staging/producción.
        Gate::define(
            'viewHorizon',
            fn (?Account $account): bool => $account?->hasRole('superadmin') ?? false,
        );
    }
}
