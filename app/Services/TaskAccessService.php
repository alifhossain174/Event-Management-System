<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class TaskAccessService
{
    public function visibleTo(User $user): Builder
    {
        $query = Task::query()
            ->whereHas('event.moduleSettings', fn (Builder $settings) => $settings
                ->where('module_key', 'tasks')->where('is_enabled', true));

        if ($user->hasPermission('tasks.view')) {
            $eventIds = app(BranchScope::class)->apply(Event::query(), $user)->select('id');

            return $query->whereIn('event_id', $eventIds);
        }

        if ($user->hasPermission('tasks.view-assigned')) {
            return $query->assignedTo($user);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canView(User $user, Task $task): bool
    {
        return $this->visibleTo($user)->whereKey($task->getKey())->exists();
    }
}
