<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** Cuentas sintéticas (persona ≠ cuenta, ADR-015). */
/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }
}
