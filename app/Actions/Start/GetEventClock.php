<?php

declare(strict_types=1);

namespace App\Actions\Start;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * ISSUE-132 (TQ-013) — Hora oficial para el reloj del tablero de salidas:
 * ms de época del servidor (referencia ServerTime, ISSUE-150) más la zona
 * del evento en la que la pantalla pinta. El navegador calcula el offset
 * (server_now − Date.now()) y deriva su reloj local, re-resincronizando
 * cada 60 s: deriva < 1 s/h por diseño (RF-42). La hora guardada al marcar
 * una salida vuelve a salir del servidor, nunca de este reloj.
 */
final readonly class GetEventClock
{
    /**
     * @return array{server_now: int, timezone: string}
     */
    public function __invoke(Event $event): array
    {
        return [
            'server_now' => (int) CarbonImmutable::now(config('app.timezone'))->getTimestampMs(),
            'timezone' => $event->timezone,
        ];
    }
}
