<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Models\Account;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/**
 * Menú del panel (ISSUE-107): se genera desde config('tc-ui.nav') y se filtra
 * por ruta existente (sin rutas fantasma, ISSUE-115) y por permiso REAL del
 * servidor. Lo no permitado no se pinta; el servidor igual lo autoriza en la
 * ruta (la UI solo oculta, ADR-011).
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

        $visible = [];

        foreach ($nav as $group) {
            $items = [];

            /** @var array<int, array<string, mixed>> $rawItems */
            $rawItems = $group['items'] ?? [];

            foreach ($rawItems as $item) {
                if (! self::routeExists((string) $item['route'])) {
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
