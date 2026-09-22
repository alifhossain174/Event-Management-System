<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class BranchFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Office';

        return [
            'company_id' => Company::factory(),
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'name' => $name,
            'city' => fake()->city(),
            'country_code' => 'US',
            'timezone' => 'UTC',
            'is_active' => true,
        ];
    }
}
