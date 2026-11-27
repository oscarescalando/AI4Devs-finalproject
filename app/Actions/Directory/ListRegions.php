<?php

declare(strict_types=1);

namespace App\Actions\Directory;

use App\Models\Region;

/**
 * Regiones activas para el selector del formulario de organizaciones
 * (ISSUE-110): id interna nunca expuesta como clave pública de ruta, solo
 * para el <select>.
 */
final readonly class ListRegions
{
    /** @return list<array{id: int, name: string}> */
    public function __invoke(): array
    {
        $regions = Region::query()->orderBy('name')->get(['id', 'name']);

        return array_values(array_map(
            fn (Region $region): array => ['id' => $region->id, 'name' => $region->name],
            $regions->all(),
        ));
    }
}
