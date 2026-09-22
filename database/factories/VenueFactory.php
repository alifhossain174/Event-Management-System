<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venue> */
final class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Venue';

        return [
            'name' => $name,
            'normalized_name' => str($name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish(),
            'type' => fake()->randomElement(['owned', 'third_party']),
            'status' => 'active',
            'capacity' => fake()->numberBetween(20, 1000),
            'city' => fake()->city(),
            'country_code' => fake()->countryCode(),
        ];
    }
}
