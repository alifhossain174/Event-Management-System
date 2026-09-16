<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClientContact> */
final class ClientContactFactory extends Factory
{
    protected $model = ClientContact::class;

    public function definition(): array
    {
        $email = fake()->safeEmail();
        $phone = fake()->numerify('+8801#########');

        return [
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'relationship_label' => fake()->randomElement(['Primary', 'Assistant', 'Family']),
            'email' => $email,
            'normalized_email' => mb_strtolower($email),
            'phone' => $phone,
            'normalized_phone' => preg_replace('/\D+/', '', $phone),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
