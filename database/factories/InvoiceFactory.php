<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'client_id' => fn (array $attributes) => Event::query()->findOrFail($attributes['event_id'])->client_id,
            'status' => 'draft', 'document_type' => 'invoice', 'currency_code' => 'USD',
            'due_date' => now()->addDays(14)->toDateString(), 'subject' => 'Event services',
            'client_name' => $this->faker->name(), 'tax_label' => 'Tax',
            'default_tax_rate' => '0.000000', 'subtotal' => '0.0000',
            'discount_total' => '0.0000', 'taxable_total' => '0.0000',
            'tax_total' => '0.0000', 'total' => '0.0000',
            'created_by_user_id' => User::factory(),
        ];
    }
}
