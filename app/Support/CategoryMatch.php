<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Categoría automática por edad y género (ISSUE-162/TQ-030): sobre las
 * categorías del evento, gana la primera cuyo token de género coincida con
 * el de la persona («M 30-39» para M) y cuyo rango cubra la edad al día del
 * evento; si ninguna coincide, cae a la primera categoría SIN token que
 * cubra la edad («Junior» 10-19 atrapa a los menores de cualquier género).
 * Las de equipos (for_teams) nunca participan de la inscripción individual
 * y los extremos null de los rangos cuentan como abiertos (CategoryRange).
 * Es la ÚNICA regla de derivación: CreateEntry y la previsualización del
 * modal la comparten para que lo que se ve antes de guardar sea exactamente
 * lo que se persiste.
 */
final class CategoryMatch
{
    /**
     * @param  Collection<int, Category>  $categories  categorías del evento (se recomienda orden por min_age, id)
     */
    public static function find(Collection $categories, string $gender, int $age): ?Category
    {
        $token = strtoupper(trim($gender));
        $token = preg_match('/^[A-Z]$/', $token) === 1 ? $token : null;

        $fallback = null;

        foreach ($categories as $category) {
            if ($category->for_teams || ! self::covers($category, $age)) {
                continue;
            }

            $nameToken = CategoryRange::genderToken((string) $category->name);

            if ($nameToken === null) {
                // Sin género en el nombre: candidata universal por edad; se
                // guarda la primera por si ninguna con token coincide.
                $fallback ??= $category;

                continue;
            }

            if ($nameToken === $token) {
                return $category;
            }
        }

        return $fallback;
    }

    /**
     * ¿El rango de la categoría cubre la edad? Rango puntual contra el de la
     * categoría reutilizando overlaps() (extremos null = abiertos).
     */
    private static function covers(Category $category, int $age): bool
    {
        return CategoryRange::overlaps(
            ['min_age' => $age, 'max_age' => $age],
            ['min_age' => $category->min_age, 'max_age' => $category->max_age],
        );
    }
}
