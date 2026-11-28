<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Enmascaramiento del documento de identidad en listados (ISSUE-161/TQ-028,
 * RNF-13): la PII fuerte nunca viaja completa en una tabla. Se conserva el
 * prefijo (V-/E-), el primer carácter tras él y los 2 últimos; cada dígito
 * oculto se sustituye por un punto medio «•» — «V-1987654» sale como
 * «V-1••••4». La misma regla rige para futuros roles restringidos y las
 * pantallas públicas: el documento completo solo se edita en el modal del
 * directorio, tras directory.view + directory.write.
 */
final class MaskDocument
{
    public static function mask(string $document): string
    {
        $split = explode('-', $document, 2);

        if (count($split) !== 2) {
            return $document;
        }

        [$prefix, $digits] = $split;

        // Con menos de 4 dígitos no queda nada razonable que conservar:
        // se ocultan todos (defensa para datos precarios, no un caso del
        // esquema — la validación exige 6-8).
        if (strlen($digits) < 4) {
            return $prefix.'-'.str_repeat('•', strlen($digits));
        }

        $hidden = strlen($digits) - 3; // 1 visible al inicio + 2 al final

        return sprintf(
            '%s-%s%s%s',
            $prefix,
            $digits[0],
            str_repeat('•', $hidden),
            substr($digits, -2),
        );
    }
}
