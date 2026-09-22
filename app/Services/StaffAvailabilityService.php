<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use Carbon\CarbonImmutable;

final class StaffAvailabilityService
{
    /** @return array{blocked: bool, conflicts: list<string>} */
    public function evaluate(
        StaffProfile $staff,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $exceptAssignmentId = null,
        ?int $exceptShiftId = null,
        bool $lock = false,
    ): array {
        $conflicts = [];

        $shifts = Shift::query()->where('staff_profile_id', $staff->getKey())
            ->whereIn('status', Shift::ACTIVE_STATUSES)
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)
            ->when($exceptShiftId, fn ($query, $id) => $query->whereKeyNot($id));
        if ($lock) {
            $shifts->lockForUpdate();
        }
        foreach ($shifts->get() as $shift) {
            $conflicts[] = "Overlaps shift {$shift->title}.";
        }

        $assignments = StaffAssignment::query()->where('staff_profile_id', $staff->getKey())
            ->whereIn('status', StaffAssignment::ACTIVE_STATUSES)
            ->where('scheduled_starts_at', '<', $endsAt)->where('scheduled_ends_at', '>', $startsAt)
            ->when($exceptAssignmentId, fn ($query, $id) => $query->whereKeyNot($id))
            ->with('event:id,reference_number,name');
        if ($lock) {
            $assignments->lockForUpdate();
        }
        foreach ($assignments->get() as $assignment) {
            $conflicts[] = "Overlaps {$assignment->event->reference_number} ({$assignment->event->name}).";
        }

        $leave = LeaveRequest::query()->where('staff_profile_id', $staff->getKey())
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $endsAt->toDateString())
            ->whereDate('ends_on', '>=', $startsAt->toDateString());
        if ($lock) {
            $leave->lockForUpdate();
        }
        foreach ($leave->get() as $request) {
            $conflicts[] = "Approved {$request->leave_type} leave covers this schedule.";
        }

        return ['blocked' => $conflicts !== [], 'conflicts' => array_values(array_unique($conflicts))];
    }
}
