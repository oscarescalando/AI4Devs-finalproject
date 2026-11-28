<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Puja horaria de las pantallas de oleadas (TQ-026/ISSUE-160): la base
 * guarda `scheduled_start_at` como datetime(3) en la zona de la app (UTC,
 * como toda marca del sistema — MarkWaveStart hace lo mismo con la salida
 * real), mientras el organizador programa en la pared horaria del evento
 * (`events.timezone`, America/Caracas). Esta clase es la única vía de ida
 * y vuelta entre ambas — la pantalla nunca convierte a mano (INV-10).
 */
final class EventTime
{
    /**
     * Interpreta una pared horaria del evento (input datetime-local,
     * admite milisegundos) y la lleva a la zona de almacenamiento.
     */
    public static function parseLocal(string $timezone, string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, $timezone)
            ->setTimezone((string) config('app.timezone'));
    }

    /**
     * Valor para input datetime-local (Y-m-d\TH:i:s.v, con ms) de un
     * instante almacenado, mostrado en la pared del evento.
     */
    public static function inputValue(string $timezone, DateTimeInterface $stored): string
    {
        return CarbonImmutable::instance($stored)
            ->setTimezone($timezone)
            ->format('Y-m-d\TH:i:s.v');
    }
}
