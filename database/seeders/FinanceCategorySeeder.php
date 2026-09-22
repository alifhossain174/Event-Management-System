<?php

namespace Database\Seeders;

use App\Models\FinanceCategory;
use Illuminate\Database\Seeder;

final class FinanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Booking payments', 'booking-payments', 'income'],
            ['Sponsorship', 'sponsorship', 'income'],
            ['Ticket sales', 'ticket-sales', 'income'],
            ['Donations', 'donations', 'income'],
            ['Venue', 'venue', 'expense'],
            ['Catering', 'catering', 'expense'],
            ['Decoration', 'decoration', 'expense'],
            ['Staff', 'staff', 'expense'],
            ['Transportation', 'transportation', 'expense'],
            ['Printing', 'printing', 'expense'],
            ['Equipment', 'equipment', 'expense'],
            ['Marketing', 'marketing', 'expense'],
            ['Miscellaneous', 'miscellaneous', 'expense'],
        ];

        foreach ($categories as $order => [$name, $slug, $direction]) {
            FinanceCategory::withTrashed()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'direction' => $direction, 'is_active' => true, 'sort_order' => ($order + 1) * 10],
            );
        }
    }
}
