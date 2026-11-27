<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lista paginada de eventos para el panel (ISSUE-115/TQ-004): el listado
 * que abre el mapa de rutas to-be. El componente Volt nunca consulta
 * modelos directamente (ADR-012: componente → Action).
 */
final readonly class ListEvents
{
    private const PER_PAGE = 10;

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    public function __invoke(?int $perPage = null): LengthAwarePaginator
    {
        return Event::query()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage ?? self::PER_PAGE);
    }
}
