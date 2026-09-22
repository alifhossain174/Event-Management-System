<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskAccessService;

final class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view') || $user->hasPermission('tasks.view-assigned');
    }

    public function view(User $user, Task $task): bool
    {
        return app(TaskAccessService::class)->canView($user, $task);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('tasks.create')
            && ! $event->isOperationallyReadOnly()
            && $user->can('viewModule', [$event, 'tasks']);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.update') && ! $task->event->isOperationallyReadOnly() && $this->view($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.assign') && ! $task->event->isOperationallyReadOnly() && $this->view($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.comment') && ! $task->event->isOperationallyReadOnly() && $this->view($user, $task);
    }

    public function complete(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.complete') && ! $task->event->isOperationallyReadOnly() && $this->view($user, $task);
    }

    public function attach(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.attach') && $user->hasPermission('documents.create')
            && ! $task->event->isOperationallyReadOnly() && $this->view($user, $task);
    }

    public function archive(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.delete') && ! $task->event->isOperationallyReadOnly() && $this->view($user, $task);
    }
}
