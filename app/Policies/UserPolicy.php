<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.update') && ! $actor->is($user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.delete') && ! $actor->is($user);
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.assign') && ! $actor->is($user);
    }

    public function changeStatus(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.update') && ! $actor->is($user);
    }
}
