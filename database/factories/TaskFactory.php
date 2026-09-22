<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'due_at' => now()->addDays(3),
            'priority' => 'normal',
            'status' => 'pending',
            'progress_percent' => 0,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => null,
        ];
    }
}
