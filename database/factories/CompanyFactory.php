<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

final class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'country_code' => 'US',
        ];
    }
}
