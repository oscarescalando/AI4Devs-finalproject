<?php

declare(strict_types=1);

namespace App\Actions\Directory\Person;

use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista paginada de personas del directorio (ISSUE-161, TQ-028): búsqueda
 * parcial por nombre (nombres, apellidos o el nombre completo concatenado) O
 * por documento — el LIKE escapa los comodines del usuario y el documento se
 * compara contra la columna real aunque la tabla lo pinte enmascarado
 * (MaskDocument): sin enumeración desde la UI.
 */
final readonly class ListPersons
{
    private const PER_PAGE = 10;

    /**
     * @param  array{q?: string}  $filters
     * @return LengthAwarePaginator<int, Person>
     */
    public function __invoke(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q);

        return Person::query()
            ->when($q !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $inner): Builder => $inner
                    ->where('first_name', 'like', '%'.$escaped.'%')
                    ->orWhere('last_name', 'like', '%'.$escaped.'%')
                    ->orWhereRaw('CONCAT(first_name, \' \', last_name) LIKE ?', ['%'.$escaped.'%'])
                    ->orWhere('document', 'like', '%'.$escaped.'%'),
            ))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($perPage ?? self::PER_PAGE);
    }
}
