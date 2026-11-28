<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista paginada de eventos para el panel (ISSUE-115/TQ-004, CRUD real en
 * TQ-025/ISSUE-160): búsqueda por nombre, orden por lista blanca (name|date)
 * y contadores de carreras e inscritos por fila — el componente Livewire
 * nunca consulta modelos directamente (ADR-012: componente → Action).
 */
final readonly class ListEvents
{
    private const SORTABLE = ['name', 'date'];

    private const PER_PAGE = 10;

    /**
     * @param  array{q?: string, sort?: string, direction?: string}  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    public function __invoke(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        // Columna fuera de la lista blanca: se ignora TODO el orden pedido
        // y cae al default (fecha descendente, el próximo primero); la
        // dirección nunca viaja sola.
        $sortable = in_array($filters['sort'] ?? null, self::SORTABLE, true);
        $sort = $sortable ? (string) $filters['sort'] : 'date';
        $direction = $sortable
            ? (strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc')
            : 'desc';
        $q = trim((string) ($filters['q'] ?? ''));

        return Event::query()
            ->withCount(['races', 'entries'])
            ->when($q !== '', fn (Builder $query): Builder => $query->where(
                'name',
                'like',
                '%'.str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q).'%',
            ))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage ?? self::PER_PAGE);
    }
}
