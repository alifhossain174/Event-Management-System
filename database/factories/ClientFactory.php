<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();
        $email = fake()->unique()->safeEmail();
        $phone = fake()->numerify('+8801#########');

        return [
            'type' => 'individual',
            'display_name' => "{$first} {$last}",
            'normalized_name' => mb_strtolower("{$first} {$last}"),
            'first_name' => $first,
            'last_name' => $last,
            'primary_email' => $email,
            'normalized_email' => mb_strtolower($email),
            'primary_phone' => $phone,
            'normalized_phone' => preg_replace('/\D+/', '', $phone),
            'status' => 'active',
            'country_code' => 'BD',
        ];
    }

    public function organization(): static
    {
        $name = fake()->company();

        return $this->state(fn () => [
            'type' => 'organization',
            'display_name' => $name,
            'normalized_name' => mb_strtolower($name),
            'first_name' => null,
            'last_name' => null,
            'organization_name' => $name,
            'legal_name' => $name,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived', 'archived_at' => now()]);
    }
}
