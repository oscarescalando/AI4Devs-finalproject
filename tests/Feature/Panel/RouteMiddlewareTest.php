<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * ISSUE-109/ISSUE-115 (TQ-008) — Inspección del mapa de rutas: recorre TODAS
 * las rutas registradas por web.php (Volt incluido) y exige el contrato de
 * autorización por ruta. Una ruta nueva sin protección rompe esta prueba
 * antes de llegar a producción; el fallo lista nombre, URI y middlewares
 * actuales. Las suites de API (sanctum) no aplican: se excluyen por prefijo.
 */
uses(RefreshDatabase::class);

/**
 * Públicas por contrato (ISSUE-137/108/114/147): landing, pantalla de
 * evento, login, estáticos/manifest del shell PWA y la estación de
 * cronometraje /station (TQ-021: captura local sin sesión, ADR-014) —
 * sin sesión ni permiso.
 *
 * @return list<string>
 */
function routeInspectionPublicUris(): array
{
    return [
        '/',
        'e/{slug}',
        'login',
        'station',
        'manifest.webmanifest',
        'pwa/manifest',
        'sw.js',
        'offline.html',
        'icons/{file}',
    ];
}

/** Rutas del flujo de sesión: auth pero sin permiso (POST /logout). */
function routeInspectionAuthFlowUris(): array
{
    return ['logout'];
}

/** Pantallas propias de la cuenta autenticada: auth sin permiso específico. */
function routeInspectionNoPermissionNames(): array
{
    return ['panel.home', 'profile'];
}

/**
 * Prefijos registrados fuera de web.php: framework (el prefijo de rutas de
 * Livewire lleva un hash: livewire-xxxx/…), api.php, sanctum, salud, más
 * dos excepciones documentadas del sustrato de operación (TQ-017):
 *
 * · horizon/* — el dashboard de colas trae SU autenticación propia
 *   (Laravel\Horizon\Http\Middleware\Authenticate), que exige la puerta
 *   viewHorizon (solo superadmin fuera de local): auth con otro nombre.
 * · broadcasting/auth — endpoint de autorización de canales (ADR-013):
 *   acepta invitados porque el contrato del canal público
 *   event.{ulid}.public se autoriza anónimo; los canales privados
 *   rechazan al invitado DENTRO del broadcaster (403, ChannelsTest),
 *   no por middleware.
 */
function routeInspectionIsExternal(string $uri): bool
{
    return (bool) preg_match('#^(livewire[^/]*|api|sanctum|storage|up|horizon|broadcasting/auth)(/|$)#', $uri);
}

/** @return list<string> */
function routeInspectionMiddleware(Route $route): array
{
    return array_values(array_map(
        static fn (mixed $middleware): string => (string) $middleware,
        $route->gatherMiddleware(),
    ));
}

it('todo lo que no es público exige sesión y permiso por ruta', function () {
    $problemas = [];

    foreach (RouteFacade::getRoutes() as $route) {
        $uri = $route->uri();
        if (routeInspectionIsExternal($uri)
            || in_array($uri, routeInspectionPublicUris(), true)
            || in_array($uri, routeInspectionAuthFlowUris(), true)) {
            continue;
        }

        $name = $route->getName() ?? '(sin nombre)';
        $middleware = routeInspectionMiddleware($route);

        if (! in_array('auth', $middleware, true)) {
            $problemas[] = sprintf('%s [%s] sin middleware auth (tiene: %s)', $name, $uri, implode(', ', $middleware) ?: 'ninguno');

            continue;
        }

        $permiso = array_filter($middleware, static fn (string $m): bool => str_starts_with($m, 'permission:'));

        if ($permiso === []
            && ! in_array($name, routeInspectionNoPermissionNames(), true)) {
            $problemas[] = sprintf('%s [%s] sin middleware permission:… (tiene: %s)', $name, $uri, implode(', ', $middleware));
        }
    }

    if ($problemas !== []) {
        $this->fail("Rutas del panel sin la protección exigida (ISSUE-109/ISSUE-115):\n".implode("\n", $problemas));
    }

    expect($problemas)->toBe([]);
});

it('las rutas públicas no exigen sesión ni permiso', function () {
    $problemas = [];

    foreach (RouteFacade::getRoutes() as $route) {
        if (! in_array($route->uri(), routeInspectionPublicUris(), true)) {
            continue;
        }

        $name = $route->getName() ?? $route->uri();
        $middleware = routeInspectionMiddleware($route);
        $prohibidos = array_filter($middleware, static fn (string $m): bool => $m === 'auth' || str_starts_with($m, 'permission:'));

        if ($prohibidos !== []) {
            $problemas[] = sprintf('%s [%s] es pública pero lleva: %s', $name, $route->uri(), implode(', ', $prohibidos));
        }
    }

    if ($problemas !== []) {
        $this->fail("Rutas públicas con autorización que no les corresponde:\n".implode("\n", $problemas));
    }

    expect($problemas)->toBe([]);
});

it('logout exige sesión pero no permiso (flujo de sesión, no panel)', function () {
    foreach (RouteFacade::getRoutes() as $route) {
        if ($route->uri() !== 'logout') {
            continue;
        }

        expect(routeInspectionMiddleware($route))->toContain('auth');

        $permiso = array_filter(routeInspectionMiddleware($route), static fn (string $m): bool => str_starts_with($m, 'permission:'));

        expect($permiso)->toBe([]);
    }
});
