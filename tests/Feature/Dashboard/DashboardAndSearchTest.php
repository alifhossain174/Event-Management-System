<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DashboardService;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DashboardAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            OrganizationSettingsSeeder::class,
            EventConfigurationSeeder::class,
        ]);
        CarbonImmutable::setTestNow('2026-09-17 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_counts_reconcile_with_filtered_event_lists(): void
    {
        $administrator = $this->userWithRole('administrator');
        $manager = $this->userWithRole('event-manager');
        $category = EventCategory::query()->firstOrFail();

        Event::factory()->create(['name' => 'Future Gala', 'event_category_id' => $category->id,
            'manager_user_id' => $manager->id, 'status' => 'draft', 'starts_at' => now()->addDay()]);
        Event::factory()->create(['name' => 'Live Gala', 'event_category_id' => $category->id,
            'manager_user_id' => $manager->id, 'status' => 'in_progress', 'starts_at' => now()->subHour()]);
        Event::factory()->create(['name' => 'Completed Gala', 'event_category_id' => $category->id,
            'manager_user_id' => $manager->id, 'status' => 'completed', 'starts_at' => now()->subDays(2)]);
        Event::factory()->create(['name' => 'Cancelled Gala', 'event_category_id' => $category->id,
            'manager_user_id' => $manager->id, 'status' => 'cancelled', 'starts_at' => now()->addDays(2)]);
        Event::factory()->create(['name' => 'Archived Gala', 'event_category_id' => $category->id,
            'manager_user_id' => $manager->id, 'archived_at' => now(), 'starts_at' => now()->addDays(3)]);

        $response = $this->actingAs($administrator)->get(route('dashboard', [
            'category' => $category->id, 'manager' => $manager->id,
        ]));
        $response->assertOk()->assertSee('Total events')->assertSee('Next upcoming events');

        $metrics = $response->viewData('metrics')->keyBy('key');
        $this->assertSame(4, $metrics['total']['count']);
        $this->assertSame(1, $metrics['upcoming']['count']);
        $this->assertSame(1, $metrics['ongoing']['count']);
        $this->assertSame(1, $metrics['completed']['count']);
        $this->assertSame(1, $metrics['cancelled']['count']);

        foreach ($metrics as $metric) {
            $this->get($metric['url'])->assertOk()->assertViewHas(
                'events',
                fn ($events) => $events->total() === $metric['count'],
            );
        }
    }

    public function test_branch_scope_restricts_dashboard_counts_filters_and_search_results(): void
    {
        $administrator = $this->userWithRole('administrator');
        $manager = $this->userWithRole('event-manager');
        app(SettingsService::class)->update(['features.branches_enabled' => true], $administrator);
        $permitted = Branch::factory()->create(['name' => 'Permitted Branch']);
        $hidden = Branch::factory()->create(['name' => 'Hidden Branch']);
        $manager->branches()->attach($permitted, ['assigned_at' => now()]);

        Event::factory()->create(['name' => 'Scope Permitted Event', 'branch_id' => $permitted->id]);
        Event::factory()->create(['name' => 'Scope Shared Event', 'branch_id' => null]);
        Event::factory()->create(['name' => 'Scope Hidden Event', 'branch_id' => $hidden->id]);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $this->assertSame(2, $response->viewData('metrics')->keyBy('key')['total']['count']);
        $response->assertSee('Permitted Branch')->assertDontSee('Hidden Branch');

        $this->get(route('dashboard', ['branch' => $hidden->id]))
            ->assertViewHas('metrics', fn ($metrics) => $metrics->keyBy('key')['total']['count'] === 0);
        $this->get(route('search', ['q' => 'Scope']))
            ->assertSee('Scope Permitted Event')->assertSee('Scope Shared Event')->assertDontSee('Scope Hidden Event');
    }

    public function test_global_search_is_grouped_bounded_escaped_and_permission_aware(): void
    {
        $administrator = $this->userWithRole('administrator');
        $eventManager = $this->userWithRole('event-manager');
        $client = Client::factory()->create(['display_name' => 'Needle Client', 'normalized_name' => 'needle client']);
        Event::factory()->create(['name' => 'Needle <script>alert(1)</script>', 'client_id' => $client->id]);
        Vendor::factory()->create(['display_name' => 'Needle Vendor', 'normalized_name' => 'needle vendor']);
        User::factory()->create(['name' => 'Needle User', 'email' => 'needle-user@example.test']);

        $this->actingAs($administrator)->get(route('search', ['q' => 'Needle']))
            ->assertOk()->assertSee('Events')->assertSee('Clients')->assertSee('Vendors')->assertSee('Users')
            ->assertSee('Needle &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('Needle <script>alert(1)</script>', false);

        $this->actingAs($eventManager)->get(route('search', ['q' => 'Needle']))
            ->assertOk()->assertSee('Needle Client')->assertSee('Needle Vendor')
            ->assertDontSee('Needle User')->assertDontSee('Users');

        $response = $this->actingAs($administrator)->get(route('search', ['q' => '%__']))->assertOk();
        $this->assertSame('', $response->viewData('term'));
        $this->assertTrue($response->viewData('groups')->isEmpty());
    }

    public function test_vendor_search_only_returns_its_linked_profile_and_dashboard_hides_unavailable_sources(): void
    {
        $vendorUser = $this->userWithRole('vendor');
        Vendor::factory()->create(['user_id' => $vendorUser->id, 'display_name' => 'Portal Needle Own', 'normalized_name' => 'portal needle own']);
        Vendor::factory()->create(['display_name' => 'Portal Needle Hidden', 'normalized_name' => 'portal needle hidden']);
        Event::factory()->create(['name' => 'Portal Needle Event']);

        $this->actingAs($vendorUser)->get(route('dashboard'))
            ->assertOk()->assertSee('Your workspace is ready')
            ->assertDontSee('Total events')->assertDontSee('Finance')->assertDontSee('Current tasks');
        $this->get(route('search', ['q' => 'Portal Needle']))
            ->assertOk()->assertSee('Portal Needle Own')->assertDontSee('Portal Needle Hidden')
            ->assertDontSee('Portal Needle Event')->assertDontSee('Events')->assertDontSee('Users');
    }

    public function test_dashboard_has_a_bounded_common_path_query_budget_and_empty_state(): void
    {
        $administrator = $this->userWithRole('administrator');
        $administrator->load('roles.permissions');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(DashboardService::class)->for($administrator, []);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queryCount, "Dashboard service used {$queryCount} queries.");
        $this->assertSame(0, $data['metrics']->keyBy('key')['total']['count']);
        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()->assertSee('No upcoming events');
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
