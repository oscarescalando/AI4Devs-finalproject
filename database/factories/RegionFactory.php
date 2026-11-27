<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Regiones sintéticas (INV-13): nombres genéricos, sin datos reales. */
/** @extends Factory<Region> */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
        ];
    }
}
