<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\BranchScope;

final class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('events.view');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasPermission('events.view') && app(BranchScope::class)->permits($user, $event->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPermission('events.update') && ! $event->isOperationallyReadOnly() && $this->view($user, $event);
    }

    public function transition(User $user, Event $event): bool
    {
        return $user->hasPermission('events.approve') && ! $event->archived_at && $event->status !== 'completed' && $this->view($user, $event);
    }

    public function correct(User $user, Event $event): bool
    {
        return $user->hasPermission('events.correct') && $event->status === 'completed' && ! $event->archived_at && $this->view($user, $event);
    }

    public function duplicate(User $user, Event $event): bool
    {
        return $user->hasPermission('events.create') && $this->view($user, $event);
    }

    public function archive(User $user, Event $event): bool
    {
        return $user->hasPermission('events.delete') && ! $event->archived_at && $this->view($user, $event);
    }

    public function reactivate(User $user, Event $event): bool
    {
        return $user->hasPermission('events.delete') && (bool) $event->archived_at && $this->view($user, $event);
    }

    public function addNote(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function manageModules(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function viewModule(User $user, Event $event, string $moduleKey): bool
    {
        if ($moduleKey === 'vendors'
            && $user->hasPermission('vendors.view-own')
            && $event->vendorAssignments()->whereHas('vendor', fn ($vendors) => $vendors->where('user_id', $user->getKey()))->exists()) {
            return true;
        }

        if ($moduleKey === 'staff'
            && $user->hasPermission('staff.view-own-work')
            && $event->staffAssignments()->whereHas('staff', fn ($staff) => $staff->where('user_id', $user->getKey()))->exists()) {
            return true;
        }

        if ($moduleKey === 'tasks'
            && $user->hasPermission('tasks.view-assigned')
            && $event->tasks()->assignedTo($user)->exists()) {
            return true;
        }

        if (! $this->view($user, $event)) {
            return false;
        }

        $permission = config("event-modules.view_permissions.{$moduleKey}");

        return is_string($permission) && $user->hasPermission($permission);
    }
}
