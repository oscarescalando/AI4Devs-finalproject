<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Wave;

/**
 * Carga del hub de evento (ISSUE-115/TQ-004): contadores de configuración
 * con los que el hub decide si muestra el aviso accionable — un evento sin
 * categorías u oleadas no puede operarse y el usuario debe poder ir a
 * configurarlas, no toparse con enlaces ausentes. Recibe el evento ya
 * resuelto por el binding ULID de la ruta; las oleadas cuelgan de las
 * carreras, por eso se cuentan a través de esa relación.
 */
final readonly class GetEventHub
{
    /**
     * @return array{races: int, categories: int, waves: int, entries: int}
     */
    public function __invoke(Event $event): array
    {
        return [
            'races' => $event->races()->count(),
            'categories' => $event->categories()->count(),
            'waves' => Wave::query()->whereRelation('race', 'event_id', $event->id)->count(),
            'entries' => $event->entries()->count(),
        ];
    }
}
