<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('master-data.view');
    }

    public function view(User $user, Model $category): bool
    {
        return $user->hasPermission('master-data.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('master-data.configure');
    }

    public function update(User $user, Model $category): bool
    {
        return $user->hasPermission('master-data.configure');
    }

    public function delete(User $user, Model $category): bool
    {
        return $user->hasPermission('master-data.configure');
    }
}
