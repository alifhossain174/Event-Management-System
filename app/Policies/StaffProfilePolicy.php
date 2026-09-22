<?php

namespace App\Policies;

use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BranchScope;

final class StaffProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view') || $user->hasPermission('staff.view-own');
    }

    public function view(User $user, StaffProfile $profile): bool
    {
        return ($user->hasPermission('staff.view') && app(BranchScope::class)->permits($user, $profile->branch_id)) || ($user->hasPermission('staff.view-own') && $profile->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.create');
    }

    public function update(User $user, StaffProfile $profile): bool
    {
        return $user->hasPermission('staff.update') && $this->view($user, $profile);
    }

    public function archive(User $user, StaffProfile $profile): bool
    {
        return $profile->record_status === 'active' && $user->hasPermission('staff.delete') && $this->view($user, $profile);
    }

    public function reactivate(User $user, StaffProfile $profile): bool
    {
        return $profile->record_status === 'archived' && $user->hasPermission('staff.delete') && $this->view($user, $profile);
    }

    public function assignUser(User $user, StaffProfile $profile): bool
    {
        return $user->hasPermission('staff.assign') && $this->view($user, $profile);
    }
}
