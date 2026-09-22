<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;

final class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function update(User $user, SystemSetting $setting): bool
    {
        return $user->hasPermission('settings.configure');
    }
}
