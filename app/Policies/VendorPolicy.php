<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Services\BranchScope;

final class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vendors.view') || $user->hasPermission('vendors.view-own');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return ($user->hasPermission('vendors.view') && app(BranchScope::class)->permits($user, $vendor->branch_id)) || ($user->hasPermission('vendors.view-own') && $vendor->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendors.create');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->hasPermission('vendors.update') && $this->view($user, $vendor);
    }

    public function archive(User $user, Vendor $vendor): bool
    {
        return $vendor->status === 'active' && $user->hasPermission('vendors.delete') && $this->view($user, $vendor);
    }

    public function reactivate(User $user, Vendor $vendor): bool
    {
        return $vendor->status === 'archived' && $user->hasPermission('vendors.delete') && $this->view($user, $vendor);
    }

    public function assignUser(User $user, Vendor $vendor): bool
    {
        return $user->hasPermission('vendors.assign') && $this->view($user, $vendor);
    }
}
