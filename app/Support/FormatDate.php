<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

/**
 * Formato único de fechas de la UI (ISSUE-118, TQ-007): la base guarda ISO
 * (`Y-m-d`, ADR-015) y la interfaz muestra `dd/mm/yyyy` (es-VE). Toda vista,
 * Action o componente Volt que necesite pintar una fecha pasa por aquí —
 * jamás un `->format('d/m/Y')` suelto.
 */
final class FormatDate
{
    public static function date(DateTimeInterface $date): string
    {
        return $date->format('d/m/Y');
    }
}
