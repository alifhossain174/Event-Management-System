<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffAssignment> */
final class StaffAssignmentFactory extends Factory
{
    protected $model = StaffAssignment::class;

    public function definition(): array
    {
        $event = Event::factory()->create();

        return [
            'event_id' => $event, 'staff_profile_id' => StaffProfile::factory(),
            'role_title' => fake()->jobTitle(), 'responsibilities' => fake()->sentence(),
            'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at,
            'status' => 'planned',
        ];
    }
}
