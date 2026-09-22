<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Event;
use App\Models\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(5, 60))->startOfHour();

        return [
            'reference_number' => 'EVT-'.now()->format('Y').'-'.str(fake()->unique()->bothify('??####'))->upper(),
            'client_id' => Client::factory(),
            'booking_id' => null,
            'event_category_id' => EventCategory::factory(),
            'name' => fake()->sentence(3),
            'status' => 'draft',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'timezone' => 'UTC',
            'expected_guest_count' => fake()->numberBetween(10, 500),
            'currency_code' => 'USD',
        ];
    }

    public function withoutClient(): static
    {
        return $this->state(fn () => ['client_id' => null]);
    }
}
