<?php

namespace App\Policies;

use App\Models\LoginHistory;
use App\Models\User;

final class LoginHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit.view');
    }

    public function view(User $user, LoginHistory $loginHistory): bool
    {
        return $user->hasPermission('audit.view');
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
