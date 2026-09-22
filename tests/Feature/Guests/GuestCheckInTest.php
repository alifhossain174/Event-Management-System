<?php

namespace Tests\Feature\Guests;

use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use App\Services\GuestInvitationService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuestCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_tokens_are_unique_non_guessable_and_qr_contains_no_guest_data(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->eventWithGuests();
        $first = Guest::factory()->create(['event_id' => $event->id, 'display_name' => 'Sensitive Guest']);
        $second = Guest::factory()->create(['event_id' => $event->id]);
        $service = app(GuestInvitationService::class);
        $one = $service->issue($first, ['delivery_channel' => 'print'], $admin);
        $two = $service->issue($second, ['delivery_channel' => 'print'], $admin);

        $this->assertNotSame($one->check_in_token_hash, $two->check_in_token_hash);
        $this->assertSame(43, strlen($one->plainToken()));
        $this->assertDatabaseCount('invitations', 2);
        $response = $this->actingAs($admin)->get(route('events.guests.invitations.qr', [$event, $first, $one]));
        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')->assertDontSee('Sensitive Guest');
        $this->assertStringNotContainsString('Sensitive Guest', $service->checkInUrl($one));

        $frontDesk = $this->userWithRole('front-desk-check-in');
        $this->actingAs($frontDesk)->get(route('events.guests.invitations.qr', [$event, $first, $one]))->assertForbidden();
    }

    public function test_front_desk_check_in_is_idempotent_and_returns_original_operator(): void
    {
        $admin = $this->userWithRole('administrator');
        $frontDesk = $this->userWithRole('front-desk-check-in');
        $event = $this->eventWithGuests();
        $guest = Guest::factory()->create(['event_id' => $event->id, 'confirmed_party_size' => 2]);
        $invitation = app(GuestInvitationService::class)->issue($guest, ['delivery_channel' => 'print'], $admin);
        $token = $invitation->plainToken();

        $this->actingAs($frontDesk)->get(route('events.guests.check-in.lookup', [$event, 'token' => $token]))
            ->assertOk()->assertSee($guest->display_name)->assertSee('Check in once');
        $this->actingAs($frontDesk)->post(route('events.guests.check-in.store', $event), ['token' => $token, 'party_size' => 2])
            ->assertOk()->assertDontSee('Already Checked In');
        $firstTime = $guest->fresh()->checkIn->checked_in_at;
        $this->actingAs($admin)->post(route('events.guests.check-in.store', $event), ['token' => $token, 'party_size' => 2])
            ->assertOk()->assertSee('Already Checked In')->assertSee($frontDesk->name);

        $this->assertDatabaseCount('guest_check_ins', 1);
        $this->assertTrue($firstTime->equalTo($guest->fresh()->checkIn->checked_in_at));
        $this->assertDatabaseHas('audit_logs', ['action' => 'guest.checked_in']);
    }

    public function test_wrong_event_revoked_and_invalid_tokens_fail_safely(): void
    {
        $admin = $this->userWithRole('administrator');
        $frontDesk = $this->userWithRole('front-desk-check-in');
        $event = $this->eventWithGuests();
        $other = $this->eventWithGuests();
        $guest = Guest::factory()->create(['event_id' => $event->id]);
        $invitationService = app(GuestInvitationService::class);
        $invitation = $invitationService->issue($guest, ['delivery_channel' => 'print'], $admin);
        $token = $invitation->plainToken();

        $this->actingAs($frontDesk)->get(route('events.guests.check-in.lookup', [$other, 'token' => $token]))
            ->assertOk()->assertSee('invalid, expired, revoked, or belongs to another Event');
        $invitationService->revoke($invitation, $admin, 'Guest is no longer attending.');
        $this->actingAs($frontDesk)->get(route('events.guests.check-in.lookup', [$event, 'token' => $token]))
            ->assertOk()->assertSee('invalid, expired, revoked, or belongs to another Event');
        $this->actingAs($frontDesk)->get(route('events.guests.check-in.lookup', [$event, 'token' => str_repeat('A', 43)]))
            ->assertOk()->assertSee('invalid, expired, revoked, or belongs to another Event');
        $this->assertDatabaseCount('guest_check_ins', 0);
    }

    public function test_user_without_front_desk_permission_cannot_check_in(): void
    {
        $staff = $this->userWithRole('staff');
        $event = $this->eventWithGuests();
        $guest = Guest::factory()->create(['event_id' => $event->id]);
        $admin = $this->userWithRole('administrator');
        $token = app(GuestInvitationService::class)->issue($guest, ['delivery_channel' => 'print'], $admin)->plainToken();

        $this->actingAs($staff)->post(route('events.guests.check-in.store', $event), ['token' => $token])->assertForbidden();
        $this->assertDatabaseCount('guest_check_ins', 0);
    }

    private function eventWithGuests(): Event
    {
        $event = Event::factory()->create(['status' => 'in_progress']);
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
