<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Event;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales de tiempo real por evento (ADR-013, RF-75 — TQ-017)
|--------------------------------------------------------------------------
| Dos canales por evento, ambos autorizados en el servidor:
|
| · `event.{ulid}.public` — feed de solo lectura y SIN PII (INV-06) para
|   pantallas públicas/kiosco. En el protocolo Pusher la suscripción a un
|   canal público no pasa por /broadcasting/auth, pero el autenticador
|   queda registrado como contrato: cualquier cliente que pida autorizar
|   el canal solo la obtiene si el ULID corresponde a un evento real (no
|   hay canales «fantasma» escaneables).
|
| · `event.{ulid}.ops` — canal PRIVADO operativo (marcas, salidas,
|   correcciones, resultados). Exige sesión con alguno de los permisos
|   operativos del catálogo (RolesPermissionsSeeder); `athlete` no tiene
|   ninguno y queda fuera.
|
| El ámbito por EQUIPO-EVENTO (roles operativos asignados por evento,
| ISSUE-115) llega en F2: hoy se valida contra los permisos globales del
| catálogo; cuando exista el contexto de equipo, el autenticador de `.ops`
| delegará en la policy del evento (ADR-013 §3).
*/

Broadcast::channel(
    'event.{ulid}.public',
    fn (?Account $account, string $ulid): bool => Event::query()->where('ulid', $ulid)->exists(),
);

Broadcast::channel(
    'event.{ulid}.ops',
    function (?Account $account, string $ulid): bool {
        if ($account === null) {
            return false;
        }

        return Event::query()->where('ulid', $ulid)->exists()
            && $account->hasAnyPermission([
                'marks.view',
                'marks.manage',
                'start.manage',
                'corrections.manage',
                'results.view',
            ]);
    },
);
