<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lista paginada de inscritos de un evento para la pantalla de
 * Inscripciones del panel (ISSUE-115/TQ-004). Mismo contrato público que
 * el roster de la estación (INV-06): dorsal, nombre público (menores con
 * iniciales) y club — sin documento, nacimiento ni contacto.
 */
final readonly class ListEventEntries
{
    private const PER_PAGE = 10;

    /**
     * @return LengthAwarePaginator<int, Entry>
     */
    public function __invoke(Event $event, ?int $perPage = null): LengthAwarePaginator
    {
        return $event->entries()
            ->with(['person', 'team.organization'])
            ->orderBy('bib')
            ->paginate($perPage ?? self::PER_PAGE);
    }
}
