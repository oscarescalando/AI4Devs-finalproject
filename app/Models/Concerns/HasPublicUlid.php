<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * ULID público con PK entera (ADR-015 / readme §3.1): la clave primaria
 * sigue siendo bigint autoincremental y las foráneas son enteras; el ULID
 * char(26) es columna UNIQUE que actúa como identificador público — el
 * único que viaja por rutas, API y broadcasts. El entero nunca sale.
 */
trait HasPublicUlid
{
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public static function bootHasPublicUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->ulid)) {
                $model->ulid = strtolower((string) Str::ulid());
            }
        });
    }
}
