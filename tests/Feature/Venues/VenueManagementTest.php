<?php

namespace Tests\Feature\Venues;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\EventVenueAllocation;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSpace;
use App\Services\AvailabilityService;
use App\Services\EventModuleDataRegistry;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class VenueManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_adjacent_allocations_are_allowed_but_overlaps_are_rejected_atomically(): void
    {
        $admin = $this->userWithRole('administrator');
        $venue = Venue::factory()->create();
        $first = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $adjacent = $this->event('2026-12-10 12:00:00', '2026-12-10 14:00:00');
        $overlap = $this->event('2026-12-10 11:59:00', '2026-12-10 13:00:00');
        $service = app(AvailabilityService::class);

        $service->allocate($first, $this->allocationData($venue), $admin);
        $service->allocate($adjacent, $this->allocationData($venue), $admin);
        $this->assertDatabaseCount('event_venue_allocations', 2);

        try {
            $service->allocate($overlap, $this->allocationData($venue), $admin);
            $this->fail('Expected overlapping allocation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('override_conflict', $exception->errors());
        }

        $this->assertDatabaseCount('event_venue_allocations', 2);
    }

    public function test_cancelled_events_do_not_reserve_availability(): void
    {
        $admin = $this->userWithRole('administrator');
        $venue = Venue::factory()->create();
        $cancelled = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00', ['status' => 'cancelled']);
        $replacement = $this->event('2026-12-10 10:30:00', '2026-12-10 11:30:00');
        $service = app(AvailabilityService::class);

        $service->allocate($cancelled, $this->allocationData($venue), $admin);
        $service->allocate($replacement, $this->allocationData($venue), $admin);

        $this->assertDatabaseCount('event_venue_allocations', 2);
    }

    public function test_different_spaces_can_be_allocated_concurrently_but_whole_venue_conflicts(): void
    {
        $admin = $this->userWithRole('administrator');
        $venue = Venue::factory()->create();
        $hallA = VenueSpace::query()->create(['venue_id' => $venue->id, 'name' => 'Hall A', 'capacity' => 100, 'is_exclusive' => true, 'is_active' => true]);
        $hallB = VenueSpace::query()->create(['venue_id' => $venue->id, 'name' => 'Hall B', 'capacity' => 100, 'is_exclusive' => true, 'is_active' => true]);
        $eventA = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $eventB = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $wholeVenue = $this->event('2026-12-10 10:30:00', '2026-12-10 11:30:00');
        $service = app(AvailabilityService::class);

        $service->allocate($eventA, $this->allocationData($venue, $hallA), $admin);
        $service->allocate($eventB, $this->allocationData($venue, $hallB), $admin);
        $this->expectException(ValidationException::class);
        $service->allocate($wholeVenue, $this->allocationData($venue), $admin);
    }

    public function test_capacity_is_a_visible_warning_not_an_allocation_blocker(): void
    {
        $admin = $this->userWithRole('administrator');
        $venue = Venue::factory()->create(['capacity' => 50]);
        $event = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00', ['expected_guest_count' => 75]);

        $allocation = app(AvailabilityService::class)->allocate($event, $this->allocationData($venue), $admin);

        $this->assertTrue($allocation->capacity_warning);
        $this->assertSame(50, $allocation->capacity_snapshot);
    }

    public function test_only_authorized_users_can_override_a_conflict_and_reason_is_audited(): void
    {
        $admin = $this->userWithRole('administrator');
        $manager = $this->userWithRole('event-manager');
        $venue = Venue::factory()->create();
        $first = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $second = $this->event('2026-12-10 11:00:00', '2026-12-10 13:00:00');
        $service = app(AvailabilityService::class);
        $service->allocate($first, $this->allocationData($venue), $admin);

        try {
            $service->allocate($second, $this->allocationData($venue) + ['override_conflict' => true, 'override_reason' => 'Approved shared loading window.'], $manager);
            $this->fail('Expected unauthorized override to fail.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('event_venue_allocations', 1);
        }

        $allocation = $service->allocate($second, $this->allocationData($venue) + ['override_conflict' => true, 'override_reason' => 'Director approved a controlled shared loading window.'], $admin);
        $this->assertTrue($allocation->conflict_override);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.venue_allocated', 'subject_id' => $allocation->id]);
        $this->assertStringContainsString('controlled shared loading window', json_encode(AuditLog::query()->where('subject_id', $allocation->id)->latest('id')->value('after_values')));
    }

    public function test_disabled_module_route_is_protected_and_allocations_are_preserved_for_reenable(): void
    {
        $admin = $this->userWithRole('administrator');
        $venue = Venue::factory()->create();
        $event = $this->event('2026-12-10 10:00:00', '2026-12-10 12:00:00');
        EventModuleSetting::query()->create(['event_id' => $event->id, 'module_key' => 'venue', 'is_enabled' => false, 'source' => 'manual']);
        EventVenueAllocation::query()->create($this->allocationData($venue) + ['event_id' => $event->id, 'created_by_user_id' => $admin->id]);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'venue'));
        $this->actingAs($admin)->get(route('events.venue.index', $event))
            ->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseCount('event_venue_allocations', 1);

        $event->moduleSettings()->where('module_key', 'venue')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.venue.index', $event))->assertOk()->assertSee($venue->name);
    }

    public function test_event_manager_can_manage_venues_but_cannot_override_conflicts(): void
    {
        $manager = $this->userWithRole('event-manager');
        $this->actingAs($manager)->post(route('venues.store'), [
            'name' => 'Riverside Hall', 'type' => 'third_party', 'capacity' => 180,
            'city' => 'Berlin', 'country_code' => 'DE',
        ])->assertSessionDoesntHaveErrors();

        $venue = Venue::query()->firstOrFail();
        $this->actingAs($manager)->get(route('venues.show', $venue))->assertOk()->assertSee('Riverside Hall');
        $this->actingAs($manager)->get(route('venues.availability', ['view' => 'calendar', 'month' => '2026-12']))
            ->assertOk()->assertSee('December 2026')->assertSee('Venue allocation calendar');
        $this->assertSame('riverside hall', $venue->normalized_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'venue.created', 'subject_id' => $venue->id]);
    }

    private function event(string $startsAt, string $endsAt, array $overrides = []): Event
    {
        return Event::factory()->create(array_replace(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => 'planning'], $overrides));
    }

    private function allocationData(Venue $venue, ?VenueSpace $space = null): array
    {
        return ['venue_id' => $venue->id, 'venue_space_id' => $space?->id, 'status' => 'planned', 'is_exclusive' => true, 'currency_code' => 'USD'];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
