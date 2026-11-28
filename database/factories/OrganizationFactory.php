<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Organizaciones sintéticas (INV-13): clubes ficticios repartidos por región. */
/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->company().' '.fake()->randomElement(['Runners', 'Trail Club', 'Team', 'Atletismo', 'Circuitos']),
        ];
    }
}
