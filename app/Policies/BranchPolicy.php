<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

final class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches.configure');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.configure');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.configure');
    }
}
