<?php

namespace App\Services;

use App\Models\Event;
use App\Models\StatusHistory;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorAssignment;
use App\Models\VendorRating;
use App\Models\VendorWorkOrder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VendorAssignmentService
{
    public function __construct(
        private readonly VendorAvailabilityService $availability,
        private readonly StatusTransitionService $statuses,
        private readonly AuditService $audit,
        private readonly BranchScope $branches,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data, User $actor): VendorAssignment
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($data['vendor_id']);
            $this->assertBranchAccess($vendor, $actor);
            $this->assertVendorAndCategory($vendor, (int) $data['vendor_category_id']);
            $this->assertApprovedCostPermission($actor, $data);
            $result = $this->availability->evaluate($vendor, $data['scheduled_starts_at'], $data['scheduled_ends_at'], null, true);

            if ($result['blocked']) {
                throw ValidationException::withMessages(['scheduled_starts_at_local' => implode(' ', $result['warnings'])]);
            }

            $assignment = VendorAssignment::query()->create($data + [
                'event_id' => $event->getKey(),
                'status' => 'draft',
                'availability_warning' => $result['warnings'] !== [],
                'availability_warning_details' => $result['warnings'] ?: null,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->recordInitialStatus($assignment, $actor, 'Vendor assigned to event');
            $this->audit->record('vendor.assignment.created', $assignment, [], $this->snapshot($assignment), $actor);
            if ($vendor->user_id && $vendor->user?->is_active) {
                $this->notifications->sendToUsers([$vendor->user], [
                    'type' => 'vendor_assignment', 'title' => 'New Event assignment',
                    'body' => 'Your vendor profile was assigned to '.$event->name.'.', 'source' => $assignment,
                    'event_id' => $event->getKey(), 'route_name' => 'events.vendors.show',
                    'route_parameters' => ['event' => $event->getKey(), 'assignment' => $assignment->getKey()],
                    'idempotency_key' => 'vendor-assignment:'.$assignment->getKey(),
                ], $actor);
            }

            return $assignment->fresh(['vendor', 'event', 'category']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(VendorAssignment $assignment, array $data, User $actor): VendorAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $locked = VendorAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($data['vendor_id']);
            $this->assertBranchAccess($vendor, $actor);
            $this->assertVendorAndCategory($vendor, (int) $data['vendor_category_id'], $locked->vendor_id === $vendor->getKey());
            $this->assertApprovedCostPermission($actor, $data, $locked);
            $result = $this->availability->evaluate($vendor, $data['scheduled_starts_at'], $data['scheduled_ends_at'], $locked->getKey(), true);

            if ($result['blocked']) {
                throw ValidationException::withMessages(['scheduled_starts_at_local' => implode(' ', $result['warnings'])]);
            }

            $before = $this->snapshot($locked);
            $locked->update($data + [
                'availability_warning' => $result['warnings'] !== [],
                'availability_warning_details' => $result['warnings'] ?: null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('vendor.assignment.updated', $locked, $before, $this->snapshot($locked), $actor);

            return $locked->fresh(['vendor', 'event', 'category']);
        }, 3);
    }

    public function transition(VendorAssignment $assignment, string $status, User $actor, ?string $reason, ?string $completionNotes): VendorAssignment
    {
        return DB::transaction(function () use ($assignment, $status, $actor, $reason, $completionNotes) {
            if (in_array($status, ['approved', 'cancelled'], true) && ! $actor->hasPermission('vendors.assign')) {
                throw new AuthorizationException('Only a manager may approve or cancel an assignment.');
            }
            if ($status === 'approved' && $assignment->approved_cost !== null && ! $actor->hasPermission('vendors.approve-cost')) {
                throw new AuthorizationException('You cannot approve vendor costs.');
            }
            if ($status === 'completed' && blank($completionNotes)) {
                throw ValidationException::withMessages(['completion_notes' => 'Completion notes are required.']);
            }

            try {
                $this->statuses->transition($assignment, $status, $actor, $reason, [], 'vendor.assignment.status_changed');
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['status' => $exception->getMessage()]);
            }

            if ($status === 'completed') {
                $assignment->update([
                    'completion_notes' => $completionNotes,
                    'completed_at' => now(),
                    'completed_by_user_id' => $actor->getKey(),
                    'delivery_status' => 'delivered',
                    'updated_by_user_id' => $actor->getKey(),
                ]);
            }

            return $assignment->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDelivery(VendorAssignment $assignment, array $data, User $actor): VendorAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $locked = VendorAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $before = ['delivery_status' => $locked->delivery_status, 'delivery_notes' => $locked->delivery_notes];
            $locked->update([
                'delivery_status' => $data['delivery_status'],
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('vendor.assignment.delivery_updated', $locked, $before, [
                'delivery_status' => $locked->delivery_status,
                'delivery_notes' => $locked->delivery_notes,
            ], $actor);

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function createWorkOrder(VendorAssignment $assignment, array $data, User $actor): VendorWorkOrder
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $workOrder = $assignment->workOrders()->create($data + [
                'reference_number' => 'WO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'status' => 'draft',
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->recordInitialStatus($workOrder, $actor, 'Work order created');
            $this->audit->record('vendor.work_order.created', $workOrder, [], [
                'assignment_id' => $assignment->getKey(), 'reference_number' => $workOrder->reference_number,
                'title' => $workOrder->title,
            ], $actor);

            return $workOrder;
        });
    }

    public function transitionWorkOrder(VendorWorkOrder $workOrder, string $status, User $actor, ?string $reason): VendorWorkOrder
    {
        try {
            $this->statuses->transition($workOrder, $status, $actor, $reason, [], 'vendor.work_order.status_changed');
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        $updates = ['updated_by_user_id' => $actor->getKey()];
        if ($status === 'issued') {
            $updates += ['issued_at' => now(), 'issued_by_user_id' => $actor->getKey()];
        }
        if ($status === 'completed') {
            $updates += ['completed_at' => now(), 'completed_by_user_id' => $actor->getKey()];
        }
        $workOrder->update($updates);

        return $workOrder->refresh();
    }

    /** @param array<string, mixed> $data */
    public function rate(VendorAssignment $assignment, array $data, User $actor): VendorRating
    {
        if ($assignment->status !== 'completed') {
            throw ValidationException::withMessages(['score' => 'Only completed assignments may be rated.']);
        }

        return DB::transaction(function () use ($assignment, $data, $actor) {
            $rating = VendorRating::query()->create([
                'vendor_assignment_id' => $assignment->getKey(),
                'vendor_id' => $assignment->vendor_id,
                'score' => $data['score'],
                'comments' => $data['comments'] ?? null,
                'reviewer_user_id' => $actor->getKey(),
                'reviewed_at' => now(),
            ]);
            $this->audit->record('vendor.assignment.rated', $rating, [], [
                'assignment_id' => $assignment->getKey(), 'vendor_id' => $assignment->vendor_id,
                'score' => $rating->score, 'reviewer_user_id' => $actor->getKey(),
            ], $actor);

            return $rating;
        });
    }

    private function assertVendorAndCategory(Vendor $vendor, int $categoryId, bool $allowArchived = false): void
    {
        if (! $allowArchived && $vendor->status !== 'active') {
            throw ValidationException::withMessages(['vendor_id' => 'Archived vendors cannot receive new assignments.']);
        }
        if (! $vendor->categories()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['vendor_category_id' => 'Choose a service category assigned to this vendor.']);
        }
    }

    private function assertBranchAccess(Vendor $vendor, User $actor): void
    {
        if (! $this->branches->permits($actor, $vendor->branch_id)) {
            throw new AuthorizationException('You cannot assign a Vendor outside your branch access.');
        }
    }

    /** @param array<string, mixed> $data */
    private function assertApprovedCostPermission(User $actor, array $data, ?VendorAssignment $assignment = null): void
    {
        $changed = array_key_exists('approved_cost', $data)
            && (string) ($data['approved_cost'] ?? '') !== (string) ($assignment?->approved_cost ?? '');

        if ($changed && ! $actor->hasPermission('vendors.approve-cost')) {
            throw new AuthorizationException('You cannot set an approved vendor cost.');
        }
    }

    private function recordInitialStatus(object $subject, User $actor, string $reason): void
    {
        StatusHistory::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'from_status' => $subject->status, 'to_status' => $subject->status, 'actor_type' => 'user',
            'actor_user_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(VendorAssignment $assignment): array
    {
        return [
            'event_id' => $assignment->event_id, 'vendor_id' => $assignment->vendor_id,
            'vendor_category_id' => $assignment->vendor_category_id, 'scope' => $assignment->scope,
            'scheduled_starts_at' => $assignment->scheduled_starts_at?->toIso8601String(),
            'scheduled_ends_at' => $assignment->scheduled_ends_at?->toIso8601String(),
            'quoted_cost' => $assignment->quoted_cost, 'approved_cost' => $assignment->approved_cost,
            'currency_code' => $assignment->currency_code, 'status' => $assignment->status,
            'delivery_status' => $assignment->delivery_status,
            'responsible_manager_user_id' => $assignment->responsible_manager_user_id,
            'availability_warning' => $assignment->availability_warning,
        ];
    }
}
