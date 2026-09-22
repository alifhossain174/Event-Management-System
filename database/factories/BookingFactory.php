<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Client;
use App\Models\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
final class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(7, 90))->startOfHour();

        return [
            'reference_number' => 'BKG-'.now()->format('Y').'-'.str(fake()->unique()->bothify('??####'))->upper(),
            'client_id' => Client::factory(),
            'requested_event_category_id' => EventCategory::factory(),
            'status' => 'enquiry',
            'requested_starts_at' => $startsAt,
            'requested_ends_at' => $startsAt->copy()->addHours(4),
            'timezone' => 'UTC',
            'venue_preference' => fake()->optional()->company().' Hall',
            'expected_guest_count' => fake()->numberBetween(10, 500),
            'budget_estimate' => fake()->randomFloat(2, 500, 50000),
            'currency_code' => 'USD',
            'notes' => fake()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed', 'approved_at' => now(), 'confirmed_at' => now()]);
    }
}
