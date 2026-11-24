<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formato único de duraciones desde milisegundos (ADR-015 / ISSUE-118):
 * `h:mm:ss` con horas sin tope (soporta > 24 h, p. ej. `54:05:34`),
 * décimas opcionales y valores negativos para diferencias.
 */
final class FormatDuration
{
    public static function fromMs(int|float $milliseconds, bool $tenths = false): string
    {
        $ms = (int) round($milliseconds);
        $sign = $ms < 0 ? '−' : '';
        $abs = abs($ms);

        $seconds = intdiv($abs, 1000);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        $text = sprintf('%d:%02d:%02d', $hours, $minutes, $secs);

        if ($tenths) {
            $text .= '.'.intdiv($abs % 1000, 100);
        }

        return $sign.$text;
    }
}
