<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\LeaveRequest;
use App\Models\PerformanceRecord;
use App\Models\SalaryRecord;
use App\Models\Shift;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Models\StatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StaffOperationsService
{
    public function __construct(
        private readonly StaffAvailabilityService $availability,
        private readonly StatusTransitionService $statuses,
        private readonly AuditService $audit,
        private readonly BranchScope $branches,
        private readonly NotificationService $notifications,
    ) {}

    public function createShift(StaffProfile $staff, array $data, User $actor): Shift
    {
        return DB::transaction(function () use ($staff, $data, $actor) {
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());
            $this->assertCanSchedule($staff, $actor);
            $result = $this->availability->evaluate($staff, $data['starts_at'], $data['ends_at'], lock: true);
            $override = $this->resolveOverride($data, $result['conflicts'], $actor);
            $shift = Shift::query()->create(collect($data)->except(['override_conflict', 'override_reason'])->all() + $override + [
                'staff_profile_id' => $staff->getKey(), 'status' => 'scheduled',
                'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->recordInitialStatus($shift, $actor, 'Shift scheduled');
            $this->audit->record('staff.shift.created', $shift, [], $this->shiftSnapshot($shift) + ['conflicts' => $result['conflicts']], $actor);

            return $shift->fresh(['staff', 'event']);
        }, 3);
    }

    public function createAssignment(Event $event, StaffProfile $staff, array $data, User $actor): StaffAssignment
    {
        return DB::transaction(function () use ($event, $staff, $data, $actor) {
            Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());
            $this->assertCanSchedule($staff, $actor);
            $result = $this->availability->evaluate($staff, $data['scheduled_starts_at'], $data['scheduled_ends_at'], lock: true);
            $override = $this->resolveOverride($data, $result['conflicts'], $actor);
            $assignment = StaffAssignment::query()->create(collect($data)->except(['override_conflict', 'override_reason'])->all() + $override + [
                'event_id' => $event->getKey(), 'staff_profile_id' => $staff->getKey(), 'status' => 'planned',
                'conflict_details' => $result['conflicts'] ?: null,
                'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->recordInitialStatus($assignment, $actor, 'Staff assigned to event');
            $this->audit->record('staff.assignment.created', $assignment, [], $this->assignmentSnapshot($assignment), $actor);
            if ($staff->user_id && $staff->user?->is_active) {
                $this->notifications->sendToUsers([$staff->user], [
                    'type' => 'staff_assignment', 'title' => 'New Event assignment',
                    'body' => 'You were assigned to '.$event->name.'.', 'source' => $assignment,
                    'event_id' => $event->getKey(), 'route_name' => 'events.staff.index',
                    'route_parameters' => ['event' => $event->getKey()],
                    'idempotency_key' => 'staff-assignment:'.$assignment->getKey(),
                ], $actor);
            }

            return $assignment->fresh(['staff', 'event']);
        }, 3);
    }

    public function transitionAssignment(StaffAssignment $assignment, string $status, User $actor, ?string $reason, ?string $completionNotes): StaffAssignment
    {
        $isOwn = $assignment->staff->user_id === $actor->getKey();
        if ($isOwn && ! $actor->hasPermission('staff.schedule')) {
            if (! $actor->hasPermission('staff.update-own-work') || ! in_array($status, ['in_progress', 'completed'], true)) {
                throw new AuthorizationException('You cannot make that assignment transition.');
            }
        } elseif (! $actor->hasPermission('staff.schedule')) {
            throw new AuthorizationException('You cannot update staff assignments.');
        }
        if ($status === 'completed' && blank($completionNotes)) {
            throw ValidationException::withMessages(['completion_notes' => 'Completion notes are required.']);
        }

        return DB::transaction(function () use ($assignment, $status, $actor, $reason, $completionNotes) {
            try {
                $this->statuses->transition($assignment, $status, $actor, $reason, [], 'staff.assignment.status_changed');
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['status' => $exception->getMessage()]);
            }
            if ($status === 'completed') {
                $assignment->update(['completion_notes' => $completionNotes, 'completed_at' => now(), 'completed_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey()]);
            }

            return $assignment->refresh();
        });
    }

    public function transitionShift(Shift $shift, string $status, User $actor, ?string $reason): Shift
    {
        $isOwn = $shift->staff->user_id === $actor->getKey();
        if (! $actor->hasPermission('staff.schedule') && (! $isOwn || ! $actor->hasPermission('staff.update-own-work') || $status !== 'completed')) {
            throw new AuthorizationException('You cannot update this shift.');
        }
        try {
            $this->statuses->transition($shift, $status, $actor, $reason, [], 'staff.shift.status_changed');
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
        if ($status === 'completed') {
            $shift->update(['completed_at' => now(), 'completed_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey()]);
        }

        return $shift->refresh();
    }

    public function requestLeave(StaffProfile $staff, array $data, User $actor): LeaveRequest
    {
        $this->assertOwnOrPermission($staff, $actor, 'staff.manage-leave', 'staff.request-leave');

        return DB::transaction(function () use ($staff, $data, $actor) {
            $request = $staff->leaveRequests()->create($data + ['status' => 'pending', 'requested_by_user_id' => $actor->getKey()]);
            $this->recordInitialStatus($request, $actor, 'Leave requested');
            $this->audit->record('staff.leave.requested', $request, [], ['staff_profile_id' => $staff->getKey(), 'starts_on' => $request->starts_on->toDateString(), 'ends_on' => $request->ends_on->toDateString()], $actor);

            return $request;
        });
    }

    public function reviewLeave(LeaveRequest $request, string $status, User $actor, ?string $notes): LeaveRequest
    {
        if (! $actor->hasPermission('staff.manage-leave')) {
            throw new AuthorizationException('You cannot review leave requests.');
        }
        try {
            $this->statuses->transition($request, $status, $actor, $notes, [], 'staff.leave.status_changed');
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
        $request->update(['reviewed_by_user_id' => $actor->getKey(), 'reviewed_at' => now(), 'review_notes' => $notes]);

        return $request->refresh();
    }

    public function recordAttendance(StaffProfile $staff, array $data, User $actor): Attendance
    {
        if (! $actor->hasPermission('staff.record-attendance')) {
            throw new AuthorizationException('You cannot record attendance.');
        }
        $this->assertCanAccess($staff, $actor);
        $this->assertNestedReferences($staff, $data);

        return DB::transaction(function () use ($staff, $data, $actor) {
            $attendance = $staff->attendances()->create($data + ['recorded_by_user_id' => $actor->getKey()]);
            $this->audit->record('staff.attendance.recorded', $attendance, [], [
                'staff_profile_id' => $staff->getKey(), 'attendance_date' => $attendance->attendance_date->toDateString(),
                'event_id' => $attendance->event_id, 'staff_assignment_id' => $attendance->staff_assignment_id, 'status' => $attendance->status,
            ], $actor);

            return $attendance;
        });
    }

    public function recordSalary(StaffProfile $staff, array $data, User $actor): SalaryRecord
    {
        if (! $actor->hasPermission('staff.manage-salary')) {
            throw new AuthorizationException('You cannot manage salary records.');
        }
        $this->assertCanAccess($staff, $actor);

        return DB::transaction(function () use ($staff, $data, $actor) {
            $record = $staff->salaryRecords()->create($data + ['recorded_by_user_id' => $actor->getKey()]);
            $this->recordInitialStatus($record, $actor, 'Salary tracking record created');
            $this->audit->record('staff.salary.recorded', $record, [], [
                'staff_profile_id' => $staff->getKey(), 'period_starts_on' => $record->period_starts_on->toDateString(),
                'period_ends_on' => $record->period_ends_on->toDateString(), 'amount' => $record->amount,
                'currency_code' => $record->currency_code, 'payment_status' => $record->payment_status,
            ], $actor);

            return $record;
        });
    }

    public function transitionSalary(SalaryRecord $record, string $status, User $actor, ?string $paidOn, ?string $reference, ?string $reason): SalaryRecord
    {
        if (! $actor->hasPermission('staff.manage-salary')) {
            throw new AuthorizationException('You cannot manage salary records.');
        }
        $this->assertCanAccess($record->staff, $actor);
        if ($status === 'paid' && blank($paidOn)) {
            throw ValidationException::withMessages(['paid_on' => 'Payment date is required when marking a record paid.']);
        }

        return DB::transaction(function () use ($record, $status, $actor, $paidOn, $reference, $reason) {
            try {
                $this->statuses->transition($record, $status, $actor, $reason, [], 'staff.salary.status_changed');
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['payment_status' => $exception->getMessage()]);
            }
            $record->update([
                'paid_on' => $status === 'paid' ? $paidOn : $record->paid_on,
                'payment_reference' => $reference ?? $record->payment_reference,
            ]);

            return $record->refresh();
        });
    }

    public function recordPerformance(StaffProfile $staff, array $data, User $actor): PerformanceRecord
    {
        if (! $actor->hasPermission('staff.manage-performance')) {
            throw new AuthorizationException('You cannot record performance.');
        }
        $this->assertCanAccess($staff, $actor);

        return DB::transaction(function () use ($staff, $data, $actor) {
            $record = $staff->performanceRecords()->create($data + ['reviewer_user_id' => $actor->getKey(), 'reviewed_at' => now()]);
            $this->audit->record('staff.performance.recorded', $record, [], [
                'staff_profile_id' => $staff->getKey(), 'event_id' => $record->event_id, 'score' => $record->score,
            ], $actor);

            return $record;
        });
    }

    private function assertCanSchedule(StaffProfile $staff, User $actor): void
    {
        if (! $actor->hasPermission('staff.schedule')) {
            throw new AuthorizationException('You cannot schedule staff.');
        }
        $this->assertCanAccess($staff, $actor);
        if ($staff->record_status !== 'active' || in_array($staff->employment_status, ['separated'], true)) {
            throw ValidationException::withMessages(['staff_profile_id' => 'Archived or separated staff cannot receive new work.']);
        }
    }

    private function assertCanAccess(StaffProfile $staff, User $actor): void
    {
        if (! $this->branches->permits($actor, $staff->branch_id)) {
            throw new AuthorizationException('You cannot manage Staff outside your branch access.');
        }
    }

    private function assertOwnOrPermission(StaffProfile $staff, User $actor, string $managerPermission, string $ownPermission): void
    {
        if ($actor->hasPermission($managerPermission)) {
            $this->assertCanAccess($staff, $actor);

            return;
        }
        if (! $actor->hasPermission($ownPermission) || $staff->user_id !== $actor->getKey()) {
            throw new AuthorizationException('You cannot manage leave for this Staff record.');
        }
    }

    private function resolveOverride(array $data, array $conflicts, User $actor): array
    {
        if ($conflicts === []) {
            return ['conflict_overridden' => false, 'conflict_override_reason' => null, 'conflict_overridden_by_user_id' => null];
        }
        if (! ($data['override_conflict'] ?? false)) {
            throw ValidationException::withMessages(['override_conflict' => implode(' ', $conflicts)]);
        }
        if (! $actor->hasPermission('staff.override-conflicts')) {
            throw new AuthorizationException('You cannot override Staff scheduling conflicts.');
        }
        if (blank($data['override_reason'] ?? null)) {
            throw ValidationException::withMessages(['override_reason' => 'An override reason is required.']);
        }

        return ['conflict_overridden' => true, 'conflict_override_reason' => $data['override_reason'], 'conflict_overridden_by_user_id' => $actor->getKey()];
    }

    private function assertNestedReferences(StaffProfile $staff, array $data): void
    {
        if (isset($data['staff_assignment_id'])) {
            $assignment = StaffAssignment::query()->findOrFail($data['staff_assignment_id']);
            if ($assignment->staff_profile_id !== $staff->getKey() || (isset($data['event_id']) && $assignment->event_id !== (int) $data['event_id'])) {
                throw ValidationException::withMessages(['staff_assignment_id' => 'The assignment does not belong to this Staff/Event context.']);
            }
        }
    }

    private function recordInitialStatus(Model $subject, User $actor, string $reason): void
    {
        $column = method_exists($subject, 'statusHistoryColumn') ? $subject->statusHistoryColumn() : 'status';
        StatusHistory::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'from_status' => $subject->getAttribute($column), 'to_status' => $subject->getAttribute($column),
            'actor_type' => 'user', 'actor_user_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => now(),
        ]);
    }

    private function shiftSnapshot(Shift $shift): array
    {
        return ['staff_profile_id' => $shift->staff_profile_id, 'event_id' => $shift->event_id, 'starts_at' => $shift->starts_at->toIso8601String(), 'ends_at' => $shift->ends_at->toIso8601String(), 'status' => $shift->status, 'conflict_overridden' => $shift->conflict_overridden];
    }

    private function assignmentSnapshot(StaffAssignment $assignment): array
    {
        return ['staff_profile_id' => $assignment->staff_profile_id, 'event_id' => $assignment->event_id, 'role_title' => $assignment->role_title, 'scheduled_starts_at' => $assignment->scheduled_starts_at->toIso8601String(), 'scheduled_ends_at' => $assignment->scheduled_ends_at->toIso8601String(), 'status' => $assignment->status, 'conflict_overridden' => $assignment->conflict_overridden];
    }
}
