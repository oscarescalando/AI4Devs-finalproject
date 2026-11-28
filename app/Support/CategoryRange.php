<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Coherencia de los rangos de edad de las categorías (TQ-026/ISSUE-160): el
 * esquema real (2026_11_24_000002) tiene `min_age`/`max_age` nullable pero
 * NO tiene columna de género — el género viaja por convención como primera
 * palabra del nombre («M 20-29», «F 40-49», sembrado así en DatabaseSeeder).
 *
 * El solapamiento solo se comprueba entre categorías del MISMO token
 * (cualquier letra única mayúscula seguida de espacio), que es lo que el
 * esquema permite distinguir: dos «M …» con rangos que se cruzan son un
 * error del operador; «M 20-29» frente a «F 20-29» es el patrón legítimo
 * M/F. Las categorías sin token («Junior», «Relevo Mixto») no participan.
 * Los huecos entre rangos quedan permitidos a propósito: un corredor fuera
 * de todo rango simplemente no clasifica en categoría por edad.
 */
final class CategoryRange
{
    /** Extremo abierto inferior: sin `min_age` el rango nace en 0. */
    private const OPEN_MIN = 0;

    /** Extremo abierto superior: sin `max_age` el rango no tiene techo. */
    private const OPEN_MAX = 1000;

    /**
     * Token de género del nombre («M 20-29» → «M»); null si no lo hay.
     */
    public static function genderToken(string $name): ?string
    {
        $first = strtok(trim($name), ' ');

        return is_string($first) && preg_match('/^[A-Z]$/', $first) === 1 ? $first : null;
    }

    /**
     * ¿Se cruzan los dos rangos? Los extremos null cuentan como abiertos.
     *
     * @param  array{min_age: int|null, max_age: int|null}  $a
     * @param  array{min_age: int|null, max_age: int|null}  $b
     */
    public static function overlaps(array $a, array $b): bool
    {
        $aMin = $a['min_age'] ?? self::OPEN_MIN;
        $aMax = $a['max_age'] ?? self::OPEN_MAX;
        $bMin = $b['min_age'] ?? self::OPEN_MIN;
        $bMax = $b['max_age'] ?? self::OPEN_MAX;

        return $aMin <= $bMax && $bMin <= $aMax;
    }
}
