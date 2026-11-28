<?php

declare(strict_types=1);

namespace App\Actions\Directory\Team;

use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista paginada de equipos del directorio (ISSUE-161, TQ-028): búsqueda
 * parcial por nombre y filtro por organización (?org=, por su ULID público
 * — ADR-015). El LIKE escapa los comodines del usuario.
 */
final readonly class ListTeams
{
    private const PER_PAGE = 10;

    /**
     * @param  array{q?: string, org?: string}  $filters
     * @return LengthAwarePaginator<int, Team>
     */
    public function __invoke(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $org = trim((string) ($filters['org'] ?? ''));

        return Team::query()
            ->with('organization')
            ->when($q !== '', fn (Builder $query): Builder => $query->where(
                'name',
                'like',
                '%'.str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q).'%',
            ))
            ->when($org !== '', fn (Builder $query): Builder => $query->whereHas(
                'organization',
                fn (Builder $inner): Builder => $inner->where('ulid', $org),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage ?? self::PER_PAGE);
    }
}
