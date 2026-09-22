<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $event = Event::factory()->create();

        return [
            'event_id' => $event->id, 'client_id' => $event->client_id,
            'receipt_number' => 'PAY-'.now()->format('Y').'-'.str()->upper(str()->random(12)),
            'payment_type' => 'advance', 'amount' => '100.0000', 'currency_code' => $event->currency_code,
            'received_at' => now(), 'received_by_user_id' => User::factory(),
            'status' => 'posted', 'channel' => 'Cash', 'entered_by_user_id' => User::factory(),
            'posted_by_user_id' => User::factory(), 'posted_at' => now(),
        ];
    }
}
