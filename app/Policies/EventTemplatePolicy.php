<?php

namespace App\Policies;

use App\Models\EventTemplate;
use App\Models\User;

final class EventTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('event-templates.view');
    }

    public function view(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission('event-templates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('event-templates.configure');
    }

    public function update(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission('event-templates.configure');
    }

    public function duplicate(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission('event-templates.configure');
    }

    public function archive(User $user, EventTemplate $template): bool
    {
        return $template->status === 'active' && $user->hasPermission('event-templates.configure');
    }

    public function reactivate(User $user, EventTemplate $template): bool
    {
        return $template->status === 'archived' && $user->hasPermission('event-templates.configure');
    }
}
