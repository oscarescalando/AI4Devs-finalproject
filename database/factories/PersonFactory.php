<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Datos 100 % sintéticos (INV-13): nunca datos reales de personas. */
/** @extends Factory<Person> */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document' => 'V-'.fake()->unique()->numberBetween(1000000, 39999999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birthdate' => fake()->dateTimeBetween('-55 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['M', 'F', 'X']),
        ];
    }

    public function minor(): static
    {
        return $this->state(fn () => [
            'birthdate' => fake()->dateTimeBetween('-17 years', '-10 years')->format('Y-m-d'),
        ]);
    }
}
