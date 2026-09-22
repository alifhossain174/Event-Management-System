<?php

namespace Tests\Feature\Bookings;

use App\Contracts\BookingConflictChecker;
use App\Contracts\BookingFinancialImpactInspector;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OptionalBookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class, EventConfigurationSeeder::class]);
    }

    public function test_manager_can_create_review_waitlist_and_confirm_with_complete_history(): void
    {
        $manager = $this->userWithRole('event-manager');
        $this->actingAs($manager)->post(route('bookings.store'), $this->validPayload())->assertSessionDoesntHaveErrors();
        $booking = Booking::query()->firstOrFail();

        $this->assertNull($booking->event_id);
        $this->actingAs($manager)->post(route('bookings.review', $booking), ['reason' => 'Scope reviewed.'])->assertSessionDoesntHaveErrors();
        $this->actingAs($manager)->post(route('bookings.waitlist', $booking), ['reason' => 'Date pending.'])->assertSessionDoesntHaveErrors();
        $this->actingAs($manager)->post(route('bookings.confirm', $booking), ['reason' => 'Date released.'])->assertSessionDoesntHaveErrors();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(
            ['enquiry', 'under_review', 'waitlisted', 'confirmed'],
            $booking->statusHistory()->reorder('changed_at')->orderBy('id')->pluck('to_status')->all(),
        );
        $this->assertSame('promoted', $booking->waitlistEntry()->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.approved_confirmed', 'subject_id' => $booking->id]);
    }

    public function test_confirmed_booking_conversion_is_transactional_linked_and_idempotent(): void
    {
        $administrator = $this->userWithRole('administrator');
        $booking = Booking::factory()->confirmed()->create();
        $payload = $this->conversionPayload($booking);

        $this->actingAs($administrator)->get(route('bookings.show', $booking))
            ->assertOk()->assertSee($booking->reference_number);
        $this->actingAs($administrator)->get(route('bookings.convert.create', $booking))
            ->assertOk()->assertSee('Convert booking to event');
        $this->actingAs($administrator)->post(route('bookings.convert.store', $booking), $payload)->assertSessionDoesntHaveErrors();
        $event = Event::query()->firstOrFail();
        $booking->refresh();
        $this->assertSame($event->id, $booking->event_id);
        $this->assertSame($booking->id, $event->booking_id);
        $this->assertSame('converted', $booking->status);
        $this->assertSame('draft', $event->status);
        $this->assertSame([], $event->enabledModuleKeys());

        $this->actingAs($administrator)->post(route('bookings.convert.store', $booking), array_replace($payload, ['enabled_modules' => null]))
            ->assertRedirect(route('events.show', $event));
        $this->assertDatabaseCount('events', 1);
        $this->assertSame(1, $booking->statusHistory()->where('to_status', 'converted')->count());
    }

    public function test_unauthorized_user_cannot_approve_or_access_booking(): void
    {
        $staff = $this->userWithRole('staff');
        $booking = Booking::factory()->create();

        $this->actingAs($staff)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($staff)->post(route('bookings.confirm', $booking))->assertForbidden();
        $this->assertSame('enquiry', $booking->fresh()->status);
    }

    public function test_known_reschedule_conflict_requires_authorized_explicit_override(): void
    {
        $this->app->instance(BookingConflictChecker::class, new class implements BookingConflictChecker
        {
            public function conflicts(Booking $booking, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $venuePreference): array
            {
                return ['Main Hall overlaps another reservation.'];
            }
        });
        $manager = $this->userWithRole('event-manager');
        $administrator = $this->userWithRole('administrator');
        $booking = Booking::factory()->confirmed()->create();
        $payload = [
            'requested_starts_at_local' => '2027-02-10T10:00',
            'requested_ends_at_local' => '2027-02-10T14:00',
            'timezone' => 'UTC',
            'venue_preference' => 'Main Hall',
            'reason' => 'Client requested another time.',
        ];

        $this->actingAs($manager)->patch(route('bookings.reschedule', $booking), $payload)
            ->assertSessionHasErrors('override_conflicts');
        $this->actingAs($manager)->patch(route('bookings.reschedule', $booking), $payload + ['override_conflicts' => 1])
            ->assertForbidden();
        $this->actingAs($administrator)->patch(route('bookings.reschedule', $booking), $payload + ['override_conflicts' => 1])
            ->assertSessionDoesntHaveErrors();

        $change = $booking->changes()->firstOrFail();
        $this->assertTrue($change->conflict_override);
        $this->assertSame(['Main Hall overlaps another reservation.'], $change->conflicts);
        $this->assertSame('2027-02-10 10:00:00', $booking->fresh()->requested_starts_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_cancellation_preserves_record_and_flags_financial_impacts(): void
    {
        $this->app->instance(BookingFinancialImpactInspector::class, new class implements BookingFinancialImpactInspector
        {
            public function cancellationFlags(Booking $booking): array
            {
                return ['payment_review_required'];
            }
        });
        $administrator = $this->userWithRole('administrator');
        $booking = Booking::factory()->confirmed()->create();

        $this->actingAs($administrator)->post(route('bookings.cancel', $booking), ['reason' => 'Client cancelled.'])
            ->assertSessionDoesntHaveErrors();
        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertTrue($booking->financial_review_required);
        $this->assertSame(['payment_review_required'], $booking->financial_flags);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'cancellation_reason' => 'Client cancelled.']);
        $this->assertDatabaseHas('booking_status_histories', ['booking_id' => $booking->id, 'to_status' => 'cancelled']);
    }

    public function test_direct_event_can_still_be_created_and_completed_without_booking(): void
    {
        $administrator = $this->userWithRole('administrator');
        $client = Client::factory()->create();
        $category = EventCategory::query()->firstOrFail();
        $payload = [
            'name' => 'Direct Minimal Event', 'client_id' => $client->id, 'event_category_id' => $category->id,
            'starts_at_local' => '2027-03-01T10:00', 'ends_at_local' => '2027-03-01T12:00',
            'timezone' => 'UTC', 'enabled_modules' => [],
        ];
        $this->actingAs($administrator)->post(route('events.store'), $payload)->assertSessionDoesntHaveErrors();
        $event = Event::query()->firstOrFail();
        foreach (['confirmed', 'planning', 'in_progress', 'completed'] as $status) {
            $this->actingAs($administrator)->patch(route('events.status', $event), ['status' => $status])->assertSessionDoesntHaveErrors();
        }

        $event->refresh();
        $this->assertNull($event->booking_id);
        $this->assertSame('completed', $event->status);
    }

    public function test_booking_list_is_filterable_paginated_and_search_is_permission_aware(): void
    {
        $administrator = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        Booking::factory()->count(21)->create();
        $target = Booking::factory()->create(['reference_number' => 'BKG-2027-UNIQUE', 'venue_preference' => 'Glass Pavilion']);

        $this->actingAs($administrator)->get(route('bookings.index', ['q' => 'Glass Pavilion']))
            ->assertOk()->assertSee('BKG-2027-UNIQUE')->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1);
        $this->actingAs($administrator)->get(route('bookings.index'))
            ->assertOk()->assertViewHas('bookings', fn ($bookings) => $bookings->perPage() === 20 && $bookings->hasPages());
        $this->actingAs($administrator)->get(route('search', ['q' => 'BKG-2027-UNIQUE']))->assertOk()->assertSee('BKG-2027-UNIQUE');
        $this->actingAs($staff)->get(route('bookings.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('search', ['q' => 'BKG-2027-UNIQUE']))
            ->assertOk()
            ->assertViewHas('groups', fn ($groups) => $groups->where('key', 'bookings')->isEmpty());
        $this->assertNotNull($target->id);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'client_id' => Client::factory()->create()->id,
            'requested_event_category_id' => EventCategory::query()->firstOrFail()->id,
            'requested_starts_at_local' => '2027-01-10T10:00',
            'requested_ends_at_local' => '2027-01-10T14:00',
            'timezone' => 'UTC',
            'venue_preference' => 'Main Hall',
            'expected_guest_count' => 100,
            'budget_estimate' => '5000.00',
            'notes' => 'Initial enquiry.',
        ];
    }

    /** @return array<string, mixed> */
    private function conversionPayload(Booking $booking): array
    {
        return [
            'name' => 'Converted Event',
            'event_category_id' => $booking->requested_event_category_id,
            'starts_at_local' => $booking->requested_starts_at->utc()->format('Y-m-d\TH:i'),
            'ends_at_local' => $booking->requested_ends_at->utc()->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'expected_guest_count' => $booking->expected_guest_count,
            'core_budget_estimate' => $booking->budget_estimate,
            'description' => $booking->notes,
            'enabled_modules' => [],
        ];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
