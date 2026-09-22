<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventBudget;
use App\Models\User;

final class EventBudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('budget.view');
    }

    public function view(User $user, EventBudget $budget): bool
    {
        return $user->hasPermission('budget.view') && $user->can('view', $budget->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('budget.manage') && ! $event->isOperationallyReadOnly() && $user->can('viewModule', [$event, 'budget']);
    }

    public function update(User $user, EventBudget $budget): bool
    {
        return $user->hasPermission('budget.manage') && $budget->status === 'draft'
            && ! $budget->event->isOperationallyReadOnly() && $this->view($user, $budget);
    }

    public function approve(User $user, EventBudget $budget): bool
    {
        return $user->hasPermission('budget.approve') && $budget->status === 'draft'
            && ! $budget->event->isOperationallyReadOnly() && $this->view($user, $budget);
    }
}
