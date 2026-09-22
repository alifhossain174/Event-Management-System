<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Expense;
use App\Models\User;

final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermission('finance.view') && $user->can('view', $expense->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('finance.create') && ! $event->isOperationallyReadOnly() && $user->can('viewModule', [$event, 'budget']);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermission('finance.create') && $expense->status === 'draft' && ! $expense->event->isOperationallyReadOnly() && $this->view($user, $expense);
    }

    public function post(User $user, Expense $expense): bool
    {
        return $user->hasPermission('finance.approve') && $expense->status === 'draft' && ! $expense->event->isOperationallyReadOnly() && $this->view($user, $expense);
    }

    public function void(User $user, Expense $expense): bool
    {
        return $user->hasPermission('finance.void') && in_array($expense->status, ['draft', 'posted'], true) && ! $expense->event->isOperationallyReadOnly() && $this->view($user, $expense);
    }
}
