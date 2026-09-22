<?php

namespace App\Policies;

use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\BranchScope;

final class StaffAssignmentPolicy
{
    public function view(User $user, StaffAssignment $assignment): bool
    {
        if ($user->hasPermission('staff.view-work') && app(BranchScope::class)->permits($user, $assignment->event->branch_id)) {
            return true;
        }

        return $user->hasPermission('staff.view-own-work') && $assignment->staff->user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.schedule');
    }

    public function update(User $user, StaffAssignment $assignment): bool
    {
        return ($user->hasPermission('staff.schedule') && $this->view($user, $assignment))
            || ($user->hasPermission('staff.update-own-work') && $assignment->staff->user_id === $user->getKey());
    }
}
