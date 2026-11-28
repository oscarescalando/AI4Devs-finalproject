<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista paginada de organizaciones para el CRUD del directorio (ISSUE-110).
 * El orden solo acepta columnas de lista blanca (name, created_at): cualquier
 * otra se IGNORA y cae al orden por defecto — nunca se pasa columna cruda
 * proveniente de la URL al SQL.
 */
final readonly class ListOrganizations
{
    private const SORTABLE = ['name', 'created_at'];

    private const PER_PAGE = 10;

    /**
     * @param  array{q?: string, sort?: string, direction?: string}  $filters
     * @return LengthAwarePaginator<int, Organization>
     */
    public function __invoke(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        // Columna fuera de la lista blanca: se ignora TODO el orden pedido
        // y cae al default (name asc); la dirección nunca viaja sola.
        $sortable = in_array($filters['sort'] ?? null, self::SORTABLE, true);
        $sort = $sortable ? (string) $filters['sort'] : 'name';
        $direction = ($sortable && strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc') ? 'desc' : 'asc';
        $q = trim((string) ($filters['q'] ?? ''));

        return Organization::query()
            ->with('region')
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
