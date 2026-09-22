<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Guest> */
final class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();
        $email = fake()->unique()->safeEmail();

        return [
            'event_id' => Event::factory(),
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => "{$first} {$last}",
            'normalized_name' => Str::lower("{$first} {$last}"),
            'email' => $email,
            'normalized_email' => Str::lower($email),
            'invitation_status' => 'not_invited',
            'rsvp_status' => 'pending',
            'plus_one_policy' => 'none',
            'plus_one_limit' => 0,
            'invited_party_size' => 1,
            'confirmed_party_size' => 0,
            'source' => 'manual',
        ];
    }
}
