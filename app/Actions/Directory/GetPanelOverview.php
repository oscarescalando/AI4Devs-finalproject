<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\TimingMark;

/**
 * Resumen de la consola del panel: contadores del sistema para el arranque
 * operativo (ISSUE-107). El componente Volt nunca consulta modelos
 * directamente (ADR-012: componente → Action).
 */
final readonly class GetPanelOverview
{
    /**
     * @return array<string, int>
     */
    public function __invoke(): array
    {
        return [
            'events' => Event::query()->count(),
            'persons' => Person::query()->count(),
            'entries' => Entry::query()->count(),
            'marks' => TimingMark::query()->count(),
        ];
    }
}
