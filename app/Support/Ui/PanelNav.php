<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Models\Account;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/**
 * Menú del panel (ISSUE-107): se genera desde config('tc-ui.nav') y se filtra
 * por ruta existente (sin rutas fantasma, ISSUE-115) y por permiso REAL del
 * servidor. Lo no permitido no se pinta; el servidor igual lo autoriza en la
 * ruta (la UI solo oculta, ADR-011).
 *
 * Rutas por evento (ISSUE-115/TQ-004): los ítems que declaran 'event' => true
 * enlazan con el ULID guardado en sesión por el hub (tc.event_ulid); sin
 * evento activo caen al listado events.index, donde el usuario elige el
 * evento a operar.
 */
final readonly class PanelNav
{
    /**
     * @return list<array<string, mixed>> grupos visibles con sus ítems
     */
    public static function visible(?Account $account): array
    {
        /** @var array<int, array<string, mixed>> $nav */
        $nav = config('tc-ui.nav', []);

        $eventUlid = self::activeEventUlid();

        $visible = [];

        foreach ($nav as $group) {
            $items = [];

            /** @var array<int, array<string, mixed>> $rawItems */
            $rawItems = $group['items'] ?? [];

            foreach ($rawItems as $item) {
                if (! self::routeExists((string) $item['route'])) {
                    continue;
                }

                // Ítem por evento sin evento activo: solo se pinta si existe
                // el listado donde elegirlo (defensivo: hoy siempre existe).
                if (self::isByEvent($item) && $eventUlid === null && ! self::routeExists('events.index')) {
                    continue;
                }

                $needsPermission = $item['permission'] !== null;
                if ($needsPermission && ($account === null || ! $account->can((string) $item['permission']))) {
                    continue;
                }

                $items[] = $item;
            }

            if ($items !== []) {
                $visible[] = ['label' => (string) $group['label'], 'items' => $items];
            }
        }

        return $visible;
    }

    /**
     * URL del ítem: las rutas por evento usan el ULID activo en sesión; sin
     * evento apuntan al listado de eventos (el hub se alcanza desde allí).
     *
     * @param  array<string, mixed>  $item
     */
    public static function href(array $item): string
    {
        $name = (string) $item['route'];

        if (self::isByEvent($item)) {
            $eventUlid = self::activeEventUlid();

            if ($eventUlid !== null) {
                return route($name, ['event' => $eventUlid]);
            }

            $name = 'events.index';
        }

        return route($name);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isByEvent(array $item): bool
    {
        return ($item['event'] ?? false) === true;
    }

    /**
     * ULID del evento activo fijado por el hub; null si no hay sesión válida.
     */
    private static function activeEventUlid(): ?string
    {
        $ulid = session('tc.event_ulid');

        return is_string($ulid) && $ulid !== '' ? $ulid : null;
    }

    /**
     * Route::has consulta un índice construido en el arranque: las rutas
     * registradas después (pruebas, proveedores tardíos) con nombre
     * fluido no aparecen. refresca el índice antes de decidir.
     */
    private static function routeExists(string $name): bool
    {
        $routes = Route::getRoutes();

        if ($routes instanceof RouteCollection) {
            $routes->refreshNameLookups();
        }

        return Route::has($name);
    }
}
