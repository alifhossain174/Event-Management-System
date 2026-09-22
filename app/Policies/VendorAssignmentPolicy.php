<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorAssignment;
use App\Services\BranchScope;

final class VendorAssignmentPolicy
{
    public function view(User $user, VendorAssignment $assignment): bool
    {
        if ($user->hasPermission('vendors.view-work')
            && app(BranchScope::class)->permits($user, $assignment->event->branch_id)) {
            return true;
        }

        return $user->hasPermission('vendors.view-own')
            && $assignment->vendor->user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendors.assign');
    }

    public function update(User $user, VendorAssignment $assignment): bool
    {
        return ($user->hasPermission('vendors.assign') && $this->view($user, $assignment))
            || ($user->hasPermission('vendors.update-work') && $assignment->vendor->user_id === $user->getKey());
    }

    public function approveCost(User $user, VendorAssignment $assignment): bool
    {
        return $user->hasPermission('vendors.approve-cost') && $this->view($user, $assignment);
    }

    public function uploadInvoice(User $user, VendorAssignment $assignment): bool
    {
        return ($user->hasPermission('vendors.upload-invoice') && $assignment->vendor->user_id === $user->getKey())
            || ($user->hasPermission('vendors.assign') && $this->view($user, $assignment));
    }

    public function rate(User $user, VendorAssignment $assignment): bool
    {
        return $user->hasPermission('vendors.rate') && $this->view($user, $assignment);
    }
}
