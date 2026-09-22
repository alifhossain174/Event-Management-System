<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vendor> */
final class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $name = fake()->company();
        $email = fake()->unique()->companyEmail();

        return ['display_name' => $name, 'normalized_name' => mb_strtolower($name), 'primary_email' => $email,
            'normalized_email' => mb_strtolower($email), 'status' => 'active'];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived', 'archived_at' => now()]);
    }
}
