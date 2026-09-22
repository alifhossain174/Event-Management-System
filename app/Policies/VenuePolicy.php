<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use App\Services\BranchScope;

final class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('venues.view');
    }

    public function view(User $user, Venue $venue): bool
    {
        return $user->hasPermission('venues.view') && app(BranchScope::class)->permits($user, $venue->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('venues.create');
    }

    public function update(User $user, Venue $venue): bool
    {
        return $venue->status === 'active' && $user->hasPermission('venues.update') && $this->view($user, $venue);
    }

    public function archive(User $user, Venue $venue): bool
    {
        return $venue->status === 'active' && $user->hasPermission('venues.delete') && $this->view($user, $venue);
    }

    public function reactivate(User $user, Venue $venue): bool
    {
        return $venue->status === 'archived' && $user->hasPermission('venues.delete') && $this->view($user, $venue);
    }
}
