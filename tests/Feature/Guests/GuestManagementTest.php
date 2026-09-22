<?php

namespace Tests\Feature\Guests;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use App\Services\EventModuleDataRegistry;
use App\Services\GuestService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuestManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_manager_creates_guest_without_login_and_filters_rsvp_group_and_vip(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithGuests();
        $group = GuestGroup::query()->create(['event_id' => $event->id, 'name' => 'Rahman Family', 'type' => 'family', 'is_vip' => true]);

        $this->actingAs($admin)->post(route('events.guests.store', $event), [
            'first_name' => 'Ayesha', 'last_name' => 'Rahman', 'email' => 'AYESHA@example.test',
            'phone' => '+49 123 456', 'is_vip' => '1', 'guest_group_id' => $group->id,
            'relationship_label' => 'Parent', 'plus_one_policy' => 'allowed', 'plus_one_limit' => 1,
            'invited_party_size' => 2, 'source' => 'manual',
        ])->assertSessionDoesntHaveErrors();

        $guest = Guest::query()->firstOrFail();
        $this->assertSame('ayesha rahman', $guest->normalized_name);
        $this->assertSame('ayesha@example.test', $guest->normalized_email);
        $this->assertNull($guest->getAttribute('user_id'));
        $this->assertSame($group->id, $guest->groupMembership->guest_group_id);

        $this->actingAs($admin)->put(route('events.guests.rsvp', [$event, $guest]), [
            'status' => 'accepted', 'attending_count' => 2, 'plus_one_count' => 1,
            'response_source' => 'phone', 'response_note' => 'Confirmed by phone.',
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($admin)->get(route('events.guests.index', [$event, 'rsvp_status' => 'accepted', 'vip' => '1', 'group_id' => $group->id]))
            ->assertOk()->assertSee('Ayesha Rahman')->assertSee('Rahman Family');
        $this->assertDatabaseHas('status_histories', ['subject_type' => 'App\\Models\\Rsvp', 'to_status' => 'accepted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guest.rsvp_recorded']);
    }

    public function test_plus_one_and_unique_seating_rules_are_enforced(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithGuests();
        $first = Guest::factory()->create(['event_id' => $event->id, 'plus_one_policy' => 'none', 'plus_one_limit' => 0]);
        $second = Guest::factory()->create(['event_id' => $event->id]);
        $family = Guest::factory()->create(['event_id' => $event->id, 'invited_party_size' => 3, 'plus_one_policy' => 'none', 'plus_one_limit' => 0]);

        $this->actingAs($admin)->put(route('events.guests.rsvp', [$event, $first]), [
            'status' => 'accepted', 'attending_count' => 2, 'plus_one_count' => 1, 'response_source' => 'manager',
        ])->assertSessionHasErrors('attending_count');

        $this->actingAs($admin)->put(route('events.guests.rsvp', [$event, $family]), [
            'status' => 'accepted', 'attending_count' => 3, 'plus_one_count' => 0, 'response_source' => 'manager',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(3, $family->fresh()->confirmed_party_size);

        $this->actingAs($admin)->put(route('events.guests.seat', [$event, $first]), [
            'table_label' => 'Table A', 'seat_label' => '1',
        ])->assertSessionDoesntHaveErrors();
        $this->actingAs($admin)->put(route('events.guests.seat', [$event, $second]), [
            'table_label' => 'Table A', 'seat_label' => '1',
        ])->assertSessionHasErrors('seat_label');
        $this->assertDatabaseCount('seat_assignments', 1);
    }

    public function test_private_notes_are_not_exposed_to_staff_or_front_desk(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $frontDesk = $this->userWithRole('front-desk-check-in');
        $event = $this->eventWithGuests();
        $guest = Guest::factory()->create(['event_id' => $event->id]);
        app(GuestService::class)->addNote($guest, ['visibility' => 'private', 'body' => 'Private family concern.'], $admin);
        app(GuestService::class)->addNote($guest, ['visibility' => 'operations', 'body' => 'Use the north entrance.'], $admin);

        $this->actingAs($admin)->get(route('events.guests.show', [$event, $guest]))->assertOk()->assertSee('Private family concern.');
        $this->actingAs($staff)->get(route('events.guests.show', [$event, $guest]))->assertOk()->assertDontSee('Private family concern.')->assertSee('Use the north entrance.');
        $this->actingAs($frontDesk)->get(route('events.guests.show', [$event, $guest]))->assertOk()->assertDontSee('Private family concern.');
    }

    public function test_capacity_warning_uses_confirmed_guest_counts_without_blocking_rsvp(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithGuests();
        $venue = Venue::factory()->create(['capacity' => 2]);
        EventModuleSetting::query()->create(['event_id' => $event->id, 'module_key' => 'venue', 'is_enabled' => true, 'source' => 'manual']);
        app(AvailabilityService::class)->allocate($event, ['venue_id' => $venue->id, 'status' => 'planned', 'is_exclusive' => true], $admin);
        Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'accepted', 'confirmed_party_size' => 3]);

        $this->actingAs($admin)->get(route('events.guests.index', $event))->assertOk()->assertSee('Capacity warning')->assertSee('3 confirmed attendees');
    }

    public function test_disabled_module_blocks_routes_and_preserves_guest_data(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithGuests();
        $guest = Guest::factory()->create(['event_id' => $event->id]);
        $event->moduleSettings()->where('module_key', 'guests')->update(['is_enabled' => false]);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'guests'));
        $this->actingAs($admin)->get(route('events.guests.index', $event))->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('guests', ['id' => $guest->id]);

        $event->moduleSettings()->where('module_key', 'guests')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.guests.index', $event))->assertOk()->assertSee($guest->display_name);
    }

    private function eventWithGuests(): Event
    {
        $event = Event::factory()->create(['status' => 'planning']);
        EventModuleSetting::query()->create(['event_id' => $event->id, 'module_key' => 'guests', 'is_enabled' => true, 'source' => 'manual']);

        return $event;
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
