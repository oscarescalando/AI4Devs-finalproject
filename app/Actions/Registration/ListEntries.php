<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Listado paginado de inscripciones del evento para el gestor del panel
 * (ISSUE-162/TQ-030): filtros combinables por carrera/categoría/estado
 * (ISSUE-128) y búsqueda «q» por dorsal EXACTO (cuando lo tecleado es
 * numérico) o nombre parcial del atleta — el LIKE escapa los comodines del
 * usuario (sin enumeración desde la UI). El atleta sale por su nombre
 * público (INV-06: los menores con iniciales); las filas de equipo muestran
 * el nombre del equipo. Orden natural por dorsal.
 */
final readonly class ListEntries
{
    private const PER_PAGE = 25;

    /**
     * @param  array{race?: string, category?: string, status?: string, q?: string}  $filters
     * @return LengthAwarePaginator<int, Entry>
     */
    public function __invoke(string $eventUlid, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $q = trim((string) ($filters['q'] ?? ''));
        $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q);
        $bib = ctype_digit($q) ? (int) $q : null;

        /** @var HasMany<Entry, Event> $query */
        $query = $event->entries()->with(['person', 'team', 'category', 'race']);

        if ($q !== '') {
            $query->where(function (Builder $inner) use ($escaped, $bib): void {
                if ($bib !== null) {
                    $inner->where('bib', $bib);
                }

                $inner->orWhereHas('person', fn (Builder $person): Builder => $person
                    ->where('first_name', 'like', '%'.$escaped.'%')
                    ->orWhere('last_name', 'like', '%'.$escaped.'%')
                    ->orWhereRaw('CONCAT(first_name, \' \', last_name) LIKE ?', ['%'.$escaped.'%']));
            });
        }

        $race = $this->present($filters, 'race');

        if ($race !== null) {
            $query->whereRelation('race', 'ulid', $race);
        }

        $category = $this->present($filters, 'category');

        if ($category !== null) {
            $query->whereRelation('category', 'ulid', $category);
        }

        $status = $this->present($filters, 'status');

        if ($status !== null) {
            $query->where('status', $status);
        }

        /** @var LengthAwarePaginator<int, Entry> $entries */
        $entries = $query->orderBy('bib')->paginate($perPage ?? self::PER_PAGE);

        return $entries;
    }

    /**
     * Valor de filtro no vacío (los «todos» viajan como cadena vacía).
     *
     * @param  array<string, mixed>  $filters
     */
    private function present(array $filters, string $key): ?string
    {
        $value = trim((string) ($filters[$key] ?? ''));

        return $value !== '' ? $value : null;
    }
}
