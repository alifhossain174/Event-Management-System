<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

final class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('settings.configure');
    }
}
