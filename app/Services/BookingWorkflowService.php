<?php

namespace App\Services;

use App\Contracts\BookingConflictChecker;
use App\Contracts\BookingFinancialImpactInspector;
use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\BookingStatusHistory;
use App\Models\Event;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BookingWorkflowService
{
    public function __construct(
        private readonly BookingReferenceService $references,
        private readonly SettingsService $settings,
        private readonly BranchScope $branches,
        private readonly BookingConflictChecker $conflicts,
        private readonly BookingFinancialImpactInspector $financialImpact,
        private readonly EventService $events,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Booking
    {
        $this->ensureBranchAccess($attributes, $actor);

        return DB::transaction(function () use ($attributes, $actor) {
            $booking = Booking::query()->create($attributes + [
                'reference_number' => $this->references->next(),
                'status' => 'enquiry',
                'currency_code' => $this->settings->string('general.currency'),
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->history($booking, null, 'enquiry', $actor, 'Booking enquiry created');
            $this->audit->record('booking.created', $booking, [], $this->snapshot($booking), $actor);
            $this->notifications->sendToRoles(['administrator', 'event-manager'], [
                'type' => 'booking_received', 'title' => 'Booking enquiry received',
                'body' => $booking->reference_number.' was created for '.$booking->client->display_name.'.',
                'source' => $booking, 'route_name' => 'bookings.show',
                'route_parameters' => ['booking' => $booking->getKey()],
                'idempotency_key' => 'booking-received:'.$booking->getKey(),
            ], $actor);

            return $booking->fresh(['client', 'category', 'branch']);
        });
    }

    public function review(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return $this->transition($booking, ['enquiry'], 'under_review', $actor, $reason ?: 'Booking review started', 'booking.review_started');
    }

    public function confirm(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureStatus($locked, ['enquiry', 'under_review', 'waitlisted']);
            $from = $locked->status;
            $now = now();
            $locked->update([
                'status' => 'confirmed',
                'approved_at' => $now,
                'approved_by_user_id' => $actor->getKey(),
                'confirmed_at' => $now,
                'confirmed_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            if ($from === 'waitlisted') {
                $locked->waitlistEntry()->where('status', 'active')->update([
                    'status' => 'promoted',
                    'promoted_at' => $now,
                    'promoted_by_user_id' => $actor->getKey(),
                ]);
            }

            $this->history($locked, $from, 'confirmed', $actor, $reason ?: 'Approved and confirmed in one manager action');
            $this->audit->record('booking.approved_confirmed', $locked, ['status' => $from], ['status' => 'confirmed'], $actor);

            return $locked->fresh();
        });
    }

    public function waitlist(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureStatus($locked, ['enquiry', 'under_review']);
            $from = $locked->status;
            $position = ((int) WaitlistEntry::query()->lockForUpdate()->where('status', 'active')->max('position')) + 1;

            $locked->waitlistEntry()->create([
                'position' => $position,
                'status' => 'active',
                'reason' => $reason,
                'added_by_user_id' => $actor->getKey(),
                'added_at' => now(),
            ]);
            $locked->update(['status' => 'waitlisted', 'updated_by_user_id' => $actor->getKey()]);
            $this->history($locked, $from, 'waitlisted', $actor, $reason ?: 'Booking added to waitlist', ['position' => $position]);
            $this->audit->record('booking.waitlisted', $locked, ['status' => $from], ['status' => 'waitlisted', 'position' => $position], $actor);

            return $locked->fresh('waitlistEntry');
        });
    }

    public function cancel(Booking $booking, User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureStatus($locked, ['enquiry', 'under_review', 'confirmed', 'waitlisted']);
            $from = $locked->status;
            $flags = $this->financialImpact->cancellationFlags($locked);

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->getKey(),
                'cancellation_reason' => $reason,
                'financial_review_required' => $flags !== [],
                'financial_flags' => $flags ?: null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $locked->waitlistEntry()->where('status', 'active')->update(['status' => 'cancelled']);
            $this->history($locked, $from, 'cancelled', $actor, $reason, ['financial_flags' => $flags]);
            $this->audit->record('booking.cancelled', $locked, ['status' => $from], [
                'status' => 'cancelled', 'reason' => $reason, 'financial_flags' => $flags,
            ], $actor);

            return $locked->fresh();
        });
    }

    public function reschedule(
        Booking $booking,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
        ?string $venuePreference,
        User $actor,
        string $reason,
        bool $override = false,
    ): Booking {
        return DB::transaction(function () use ($booking, $startsAt, $endsAt, $timezone, $venuePreference, $actor, $reason, $override) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureStatus($locked, ['enquiry', 'under_review', 'confirmed', 'waitlisted']);
            $conflicts = $this->conflicts->conflicts($locked, $startsAt, $endsAt, $venuePreference);

            if ($conflicts !== [] && ! $override) {
                throw ValidationException::withMessages([
                    'override_conflicts' => 'The requested schedule conflicts with known resources. An authorized override is required.',
                    'conflicts' => $conflicts,
                ]);
            }
            if ($conflicts !== [] && ! $actor->hasPermission('bookings.override-conflicts')) {
                throw new AuthorizationException('You are not authorized to override Booking conflicts.');
            }

            $before = $this->scheduleSnapshot($locked);
            $locked->update([
                'requested_starts_at' => $startsAt,
                'requested_ends_at' => $endsAt,
                'timezone' => $timezone,
                'venue_preference' => $venuePreference,
                'rescheduled_at' => now(),
                'rescheduled_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $after = $this->scheduleSnapshot($locked);
            $this->change($locked, 'rescheduled', $before, $after, $actor, $reason, $conflicts, $override);
            $this->audit->record('booking.rescheduled', $locked, $before, $after + [
                'conflicts' => $conflicts, 'conflict_override' => $override,
            ], $actor);

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $eventAttributes
     * @param  list<string>  $enabledModules
     */
    public function convert(Booking $booking, array $eventAttributes, array $enabledModules, User $actor): Event
    {
        return DB::transaction(function () use ($booking, $eventAttributes, $enabledModules, $actor) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if ($locked->event_id) {
                return Event::query()->findOrFail($locked->event_id);
            }
            $this->ensureStatus($locked, ['confirmed']);

            $event = $this->events->create($eventAttributes + [
                'booking_id' => $locked->getKey(),
                'client_id' => $locked->client_id,
            ], $enabledModules, $actor);
            $from = $locked->status;
            $locked->update([
                'event_id' => $event->getKey(),
                'status' => 'converted',
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->history($locked, $from, 'converted', $actor, 'Booking converted to Event', ['event_id' => $event->getKey()]);
            $this->change($locked, 'converted', ['event_id' => null], ['event_id' => $event->getKey()], $actor, 'Booking converted to Event');
            $this->audit->record('booking.converted', $locked, ['event_id' => null, 'status' => $from], [
                'event_id' => $event->getKey(), 'status' => 'converted',
            ], $actor);

            return $event->fresh();
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(Booking $booking): array
    {
        return [
            'reference_number' => $booking->reference_number,
            'client_id' => $booking->client_id,
            'requested_event_category_id' => $booking->requested_event_category_id,
            'branch_id' => $booking->branch_id,
            'event_id' => $booking->event_id,
            'status' => $booking->status,
            'requested_starts_at' => $booking->requested_starts_at?->toIso8601String(),
            'requested_ends_at' => $booking->requested_ends_at?->toIso8601String(),
            'expected_guest_count' => $booking->expected_guest_count,
            'budget_estimate' => $booking->budget_estimate,
        ];
    }

    /** @param list<string> $fromStatuses */
    private function transition(
        Booking $booking,
        array $fromStatuses,
        string $toStatus,
        User $actor,
        string $reason,
        string $auditAction,
    ): Booking {
        return DB::transaction(function () use ($booking, $fromStatuses, $toStatus, $actor, $reason, $auditAction) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureStatus($locked, $fromStatuses);
            $from = $locked->status;
            $locked->update(['status' => $toStatus, 'updated_by_user_id' => $actor->getKey()]);
            $this->history($locked, $from, $toStatus, $actor, $reason);
            $this->audit->record($auditAction, $locked, ['status' => $from], ['status' => $toStatus], $actor);

            return $locked->fresh();
        });
    }

    /** @param list<string> $allowed */
    private function ensureStatus(Booking $booking, array $allowed): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This Booking action is not available from '.str($booking->status)->headline().'.',
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function history(
        Booking $booking,
        ?string $from,
        string $to,
        User $actor,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        BookingStatusHistory::query()->create([
            'booking_id' => $booking->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => 'user',
            'actor_user_id' => $actor->getKey(),
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'changed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $conflicts
     */
    private function change(
        Booking $booking,
        string $type,
        array $before,
        array $after,
        User $actor,
        ?string $reason = null,
        array $conflicts = [],
        bool $override = false,
    ): void {
        BookingChange::query()->create([
            'booking_id' => $booking->getKey(),
            'type' => $type,
            'before_values' => $before ?: null,
            'after_values' => $after ?: null,
            'conflicts' => $conflicts ?: null,
            'conflict_override' => $override,
            'reason' => $reason,
            'actor_user_id' => $actor->getKey(),
            'occurred_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function scheduleSnapshot(Booking $booking): array
    {
        return [
            'requested_starts_at' => $booking->requested_starts_at?->toIso8601String(),
            'requested_ends_at' => $booking->requested_ends_at?->toIso8601String(),
            'timezone' => $booking->timezone,
            'venue_preference' => $booking->venue_preference,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function ensureBranchAccess(array $attributes, User $actor): void
    {
        $branchId = isset($attributes['branch_id']) ? (int) $attributes['branch_id'] : null;
        if (! $this->branches->permits($actor, $branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'You cannot assign a Booking to that branch.']);
        }
    }
}
