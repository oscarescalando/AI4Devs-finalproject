<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Formato único de horas del día de la UI (ISSUE-130, TQ-012): la captura
 * pinta la hora oficial de cada llegada con milisegundos (`H:i:s.v`) y en
 * la zona del evento (spec/09-ui-estilo.md §8: hora de Caracas en
 * pantalla). No es una duración: para `h:mm:ss` de tiempos netos está
 * FormatDuration, única vía (ADR-015).
 */
final class FormatClock
{
    /**
     * @param  DateTimeInterface|string  $time  hora ya resuelta o la cadena que persiste timing_marks.
     * @param  string|null  $timezone  zona destino (p. ej. la del evento); null la deja como viene.
     */
    public static function timeOfDay(DateTimeInterface|string $time, ?string $timezone = null): string
    {
        $moment = $time instanceof DateTimeInterface
            ? CarbonImmutable::instance($time)
            : CarbonImmutable::parse($time);

        if ($timezone !== null && $timezone !== '') {
            $moment = $moment->setTimezone($timezone);
        }

        return $moment->format('H:i:s.v');
    }
}
