<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventLifecycleService
{
    private const TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['planning', 'cancelled'],
        'planning' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['planning'],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly EventTimelineService $timeline,
    ) {}

    /** @return list<string> */
    public function availableTransitions(Event $event): array
    {
        if ($event->archived_at) {
            return [];
        }

        return self::TRANSITIONS[$event->status] ?? [];
    }

    public function transition(Event $event, string $toStatus, User $actor, ?string $reason = null): void
    {
        if ($event->status === 'completed') {
            throw ValidationException::withMessages(['status' => 'Completed Events can only be reopened through the privileged correction action.']);
        }

        $this->apply($event, $toStatus, $actor, $reason, false);
    }

    public function reopenForCorrection(Event $event, User $actor, string $reason): void
    {
        if ($event->status !== 'completed') {
            throw ValidationException::withMessages(['status' => 'Only a completed Event can be reopened for correction.']);
        }

        $this->apply($event, 'planning', $actor, $reason, true);
    }

    private function apply(Event $event, string $toStatus, User $actor, ?string $reason, bool $correction): void
    {
        DB::transaction(function () use ($event, $toStatus, $actor, $reason, $correction) {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $fromStatus = $locked->status;
            $allowed = self::TRANSITIONS[$fromStatus] ?? [];

            if ($locked->archived_at || ! in_array($toStatus, $allowed, true)) {
                throw ValidationException::withMessages(['status' => "Transition from {$fromStatus} to {$toStatus} is not allowed."]);
            }
            if ($fromStatus === 'draft' && $toStatus !== 'cancelled' && ! $locked->client_id) {
                throw ValidationException::withMessages(['client_id' => 'Assign a Client before the Event leaves Draft.']);
            }
            if ($toStatus === 'cancelled' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A cancellation reason is required.']);
            }

            $changes = ['status' => $toStatus, 'updated_by_user_id' => $actor->getKey()];
            if ($toStatus === 'completed') {
                $changes += ['completed_at' => now(), 'completed_by_user_id' => $actor->getKey()];
            }
            if ($toStatus === 'cancelled') {
                $changes += ['cancelled_at' => now(), 'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason];
            }
            if ($correction) {
                $changes += ['completed_at' => null, 'completed_by_user_id' => null];
            }

            $locked->update($changes);
            EventStatusHistory::query()->create([
                'event_id' => $locked->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => 'user',
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
                'metadata' => $correction ? ['correction' => true] : null,
                'changed_at' => now(),
            ]);
            $action = $correction ? 'event.corrected' : 'event.status_changed';
            $this->audit->record($action, $locked, ['status' => $fromStatus], ['status' => $toStatus, 'reason' => $reason], $actor);
            $this->timeline->record($locked, $correction ? 'correction' : 'status', $correction ? 'Event reopened for correction' : 'Status changed to '.str($toStatus)->headline(), $actor, $reason, [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ]);
        });

        $event->refresh();
    }
}
