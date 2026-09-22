<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffProfile> */
final class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();
        $email = fake()->unique()->safeEmail();

        return ['first_name' => $first, 'last_name' => $last, 'display_name' => "$first $last",
            'normalized_name' => mb_strtolower("$first $last"), 'email' => $email, 'normalized_email' => mb_strtolower($email),
            'employment_status' => 'active', 'record_status' => 'active'];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['record_status' => 'archived', 'archived_at' => now()]);
    }
}
