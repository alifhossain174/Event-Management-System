<?php

namespace Tests\Feature\Calendar;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\EventVenueAllocation;
use App\Models\Role;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\VendorAssignment;
use App\Models\Venue;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CalendarProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_daily_weekly_and_monthly_windows_include_only_matching_source_dates(): void
    {
        $admin = $this->userWithRole('administrator');
        $inside = $this->event('Inside period', '2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $outside = $this->event('Outside period', '2027-01-10 10:00:00', '2027-01-10 12:00:00');
        Task::factory()->create(['event_id' => $inside, 'title' => 'Calendar deadline', 'due_at' => '2026-12-10 09:00:00']);

        foreach (['daily', 'weekly', 'monthly'] as $view) {
            $this->actingAs($admin)->get(route('calendar.index', ['view' => $view, 'date' => '2026-12-10']))
                ->assertOk()->assertSee('Inside period')->assertSee('Calendar deadline')->assertDontSee('Outside period');
        }
    }

    public function test_staff_calendar_visibility_and_click_through_are_assignment_scoped(): void
    {
        $portal = $this->userWithRole('staff');
        $otherPortal = $this->userWithRole('staff');
        $staff = StaffProfile::factory()->create(['user_id' => $portal->id]);
        $other = StaffProfile::factory()->create(['user_id' => $otherPortal->id]);
        $event = $this->event('Assigned event', '2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $task = Task::factory()->create(['event_id' => $event, 'title' => 'Private assigned deadline', 'due_at' => '2026-12-10 09:00:00']);
        TaskAssignment::query()->create(['task_id' => $task->id, 'staff_profile_id' => $staff->id, 'assigned_at' => now()]);
        StaffAssignment::factory()->create(['event_id' => $event, 'staff_profile_id' => $staff, 'role_title' => 'Assigned role', 'scheduled_starts_at' => '2026-12-10 10:00:00', 'scheduled_ends_at' => '2026-12-10 12:00:00']);
        StaffAssignment::factory()->create(['event_id' => $event, 'staff_profile_id' => $other, 'role_title' => 'Other role', 'scheduled_starts_at' => '2026-12-10 10:00:00', 'scheduled_ends_at' => '2026-12-10 12:00:00']);

        $this->actingAs($portal)->get(route('calendar.index', ['view' => 'daily', 'date' => '2026-12-10']))
            ->assertOk()->assertSee('Private assigned deadline')->assertSee('Assigned role')->assertDontSee('Other role')
            ->assertSee(route('events.tasks.show', [$event, $task]), false);
        $this->actingAs($portal)->get(route('events.tasks.show', [$event, $task]))->assertOk();
        $this->actingAs($otherPortal)->get(route('events.tasks.show', [$event, $task]))->assertForbidden();
    }

    public function test_calendar_combines_each_current_schedule_source_without_copying_rows(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = StaffProfile::factory()->create();
        $event = $this->event('Source event', '2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $venue = Venue::factory()->create(['name' => 'Projection Hall']);
        EventVenueAllocation::query()->create([
            'event_id' => $event->id, 'venue_id' => $venue->id, 'status' => 'planned', 'is_exclusive' => true,
        ]);
        $vendor = VendorAssignment::factory()->create([
            'event_id' => $event, 'status' => 'approved',
            'scheduled_starts_at' => '2026-12-10 08:00:00', 'scheduled_ends_at' => '2026-12-10 09:00:00',
        ])->vendor;
        StaffAssignment::factory()->create([
            'event_id' => $event, 'staff_profile_id' => $staff, 'role_title' => 'Calendar coordinator',
            'scheduled_starts_at' => '2026-12-10 09:00:00', 'scheduled_ends_at' => '2026-12-10 13:00:00',
        ]);
        Task::factory()->create(['event_id' => $event, 'title' => 'Calendar task', 'due_at' => '2026-12-10 07:00:00']);

        $this->actingAs($admin)->get(route('calendar.index', ['view' => 'daily', 'date' => '2026-12-10']))
            ->assertOk()->assertSee('Source event')->assertSee('Projection Hall')
            ->assertSee($vendor->display_name)->assertSee('Calendar coordinator')->assertSee('Calendar task');
        $this->assertDatabaseHas('event_venue_allocations', ['event_id' => $event->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_tasks', ['event_id' => $event->id, 'title' => 'Calendar task']);
    }

    public function test_disabled_sources_are_omitted_without_deleting_and_no_calendar_table_exists(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event('Disabled tasks event', '2026-12-10 10:00:00', '2026-12-10 12:00:00');
        $task = Task::factory()->create(['event_id' => $event, 'title' => 'Preserved hidden deadline', 'due_at' => '2026-12-10 09:00:00']);
        $event->moduleSettings()->where('module_key', 'tasks')->update(['is_enabled' => false]);

        $this->actingAs($admin)->get(route('calendar.index', ['view' => 'daily', 'date' => '2026-12-10', 'source' => 'tasks']))
            ->assertOk()->assertDontSee('Preserved hidden deadline');
        $this->assertDatabaseHas('event_tasks', ['id' => $task->id]);
        $this->assertFalse(Schema::hasTable('calendar_events'));
        $this->assertFalse(Schema::hasTable('schedule_entries'));
    }

    private function event(string $name, string $starts, string $ends): Event
    {
        $event = Event::factory()->create(['name' => $name, 'starts_at' => $starts, 'ends_at' => $ends, 'timezone' => 'UTC', 'status' => 'planning']);
        foreach (['tasks', 'staff', 'venue', 'vendors'] as $key) {
            EventModuleSetting::query()->updateOrCreate(['event_id' => $event->id, 'module_key' => $key], ['is_enabled' => true, 'source' => 'manual']);
        }

        return $event;
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
