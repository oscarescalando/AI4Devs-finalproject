<?php

declare(strict_types=1);

namespace App\Support\Ui;

/**
 * Estado del evento (draft|registration|live|finished|closed) visto por la
 * UI: etiqueta en español (lang/es/ui.php, TQ-007) y variante de badge del
 * kit — el color comunica estado y el código crudo nunca se pinta
 * (spec/09-ui-estilo.md §1/§3).
 */
final readonly class EventStatus
{
    /**
     * Catálogo de estados del esquema (events.status, ISSUE-157): única
     * fuente para validar en las Actions del CRUD (TQ-025) y para pintar
     * el selector de estado del formulario del evento.
     *
     * @var list<string>
     */
    public const STATUSES = ['draft', 'registration', 'live', 'finished', 'closed'];

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
        $key = "ui.events.status.{$status}";

        $label = __($key);

        // Sin etiqueta (código nuevo sin traducir) se pinta el código crudo,
        // como antes de la migración a lang/.
        if (! is_string($label) || $label === $key) {
            return $status;
        }

        return $label;
    }

    public static function badgeVariant(string $status): string
    {
        return self::BADGES[$status] ?? 'neutral';
    }
}
