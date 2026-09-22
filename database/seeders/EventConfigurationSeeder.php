<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ModuleDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EventConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (config('event-modules.definitions') as $key => $definition) {
                ModuleDefinition::query()->firstOrCreate(['key' => $key], [
                    'label' => $definition['label'],
                    'scope' => $definition['scope'],
                    'is_event_scoped' => $definition['event_toggle'],
                    'is_active' => true,
                    'sort_order' => $definition['order'],
                ]);
            }

            foreach ($this->templates() as $definition) {
                $category = EventCategory::withTrashed()->firstOrCreate(
                    ['slug' => $definition['slug']],
                    ['name' => $definition['category'], 'description' => $definition['description'], 'is_active' => true, 'sort_order' => $definition['order']],
                );

                $template = EventTemplate::query()->firstOrCreate(['slug' => $definition['slug']], [
                    'event_category_id' => $category->getKey(),
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'service_notes' => $definition['service_notes'],
                    'starter_tasks' => [],
                    'budget_lines' => [],
                    'status' => 'active',
                ]);

                if ($template->wasRecentlyCreated) {
                    $order = 0;
                    foreach ($definition['default'] as $key) {
                        $template->modules()->create(['module_key' => $key, 'recommendation' => 'default', 'sort_order' => $order++]);
                    }
                    foreach ($definition['optional'] as $key) {
                        $template->modules()->create(['module_key' => $key, 'recommendation' => 'optional', 'sort_order' => $order++]);
                    }
                }
            }
        });
    }

    /** @return list<array<string, mixed>> */
    private function templates(): array
    {
        return [
            ['name' => 'Birthday Party', 'category' => 'Birthday Party', 'slug' => 'birthday-party', 'order' => 10,
                'description' => 'Editable starting point for birthday events.',
                'default' => ['venue', 'catering', 'decoration', 'vendors', 'staff', 'tasks', 'budget', 'payments', 'invoices'],
                'optional' => ['guests', 'inventory', 'documents', 'transportation'],
                'service_notes' => 'Typical optional add-ons: guest list, inventory, documents, and transportation.'],
            ['name' => 'Wedding/Marriage Ceremony', 'category' => 'Wedding/Marriage Ceremony', 'slug' => 'wedding-marriage-ceremony', 'order' => 20,
                'description' => 'Editable starting point for wedding and marriage ceremonies.',
                'default' => ['venue', 'catering', 'decoration', 'guests', 'vendors', 'staff', 'tasks', 'budget', 'payments', 'invoices', 'inventory'],
                'optional' => ['transportation', 'accommodation', 'registration', 'documents', 'marketing'],
                'service_notes' => 'Typical optional add-ons: transportation, accommodation, registration, documents, and marketing.'],
            ['name' => 'Corporate/Seminar/Workshop', 'category' => 'Corporate/Seminar/Workshop', 'slug' => 'corporate-seminar-workshop', 'order' => 30,
                'description' => 'Editable starting point for corporate events, seminars, and workshops.',
                'default' => ['venue', 'staff', 'tasks', 'registration', 'budget', 'invoices', 'documents'],
                'optional' => ['guests', 'catering', 'ticketing', 'vendors', 'inventory', 'marketing'],
                'service_notes' => 'Registration or Guest List can be selected to suit the event. Typical add-ons include catering, ticketing, vendors, inventory, and marketing.'],
            ['name' => 'Concert/Exhibition/Festival', 'category' => 'Concert/Exhibition/Festival', 'slug' => 'concert-exhibition-festival', 'order' => 40,
                'description' => 'Editable starting point for concerts, exhibitions, and festivals.',
                'default' => ['venue', 'vendors', 'staff', 'tasks', 'ticketing', 'registration', 'guests', 'inventory', 'budget', 'payments'],
                'optional' => ['marketing', 'transportation', 'documents'],
                'service_notes' => 'Typical optional add-ons: marketing, transportation, and documents.'],
            ['name' => 'Custom Event', 'category' => 'Custom Event', 'slug' => 'custom-event', 'order' => 50,
                'description' => 'Blank starting point where the manager selects every required module.',
                'default' => [], 'optional' => [],
                'service_notes' => 'No forced preset. Any available module may be selected manually.'],
        ];
    }
}
