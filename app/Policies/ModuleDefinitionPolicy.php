<?php

namespace App\Policies;

use App\Models\ModuleDefinition;
use App\Models\User;

final class ModuleDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('event-templates.view');
    }

    public function view(User $user, ModuleDefinition $definition): bool
    {
        return $user->hasPermission('event-templates.view');
    }

    public function update(User $user, ModuleDefinition $definition): bool
    {
        return $user->hasPermission('event-templates.configure');
    }
}
