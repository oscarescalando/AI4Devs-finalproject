<?php

declare(strict_types=1);

namespace App\Support\Ui;

/**
 * Estado del evento (draft|registration|live|finished|closed) visto por la
 * UI: etiqueta en español y variante de badge del kit — el color comunica
 * estado y el código crudo nunca se pinta (spec/09-ui-estilo.md §1/§3).
 */
final readonly class EventStatus
{
    /** @var array<string, string> */
    private const LABELS = [
        'draft' => 'Borrador',
        'registration' => 'Inscripción',
        'live' => 'En vivo',
        'finished' => 'Finalizado',
        'closed' => 'Cerrado',
    ];

    /** @var array<string, string> */
    private const BADGES = [
        'draft' => 'neutral',
        'registration' => 'info',
        'live' => 'success',
        'finished' => 'warning',
        'closed' => 'primary',
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function badgeVariant(string $status): string
    {
        return self::BADGES[$status] ?? 'neutral';
    }
}
