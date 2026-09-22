<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Vendor;
use App\Models\VendorAssignment;
use App\Models\VendorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VendorAssignment> */
final class VendorAssignmentFactory extends Factory
{
    protected $model = VendorAssignment::class;

    public function definition(): array
    {
        $event = Event::factory()->create();

        return [
            'event_id' => $event,
            'vendor_id' => Vendor::factory(),
            'vendor_category_id' => fn () => VendorCategory::query()->firstOrCreate(
                ['slug' => 'general-services'],
                ['name' => 'General Services', 'description' => null, 'is_active' => true, 'sort_order' => 0],
            )->getKey(),
            'scope' => fake()->sentence(),
            'scheduled_starts_at' => $event->starts_at,
            'scheduled_ends_at' => $event->ends_at,
            'currency_code' => $event->currency_code,
            'status' => 'draft',
            'delivery_status' => 'pending',
        ];
    }
}
