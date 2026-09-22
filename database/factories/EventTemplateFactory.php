<?php

namespace Database\Factories;

use App\Models\EventTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EventTemplate> */
final class EventTemplateFactory extends Factory
{
    protected $model = EventTemplate::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(),
            'starter_tasks' => [],
            'budget_lines' => [],
            'status' => 'active',
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived', 'archived_at' => now()]);
    }
}
