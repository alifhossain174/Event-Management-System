<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Income;
use App\Models\User;

final class IncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, Income $income): bool
    {
        return $user->hasPermission('finance.view') && $user->can('view', $income->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('finance.create') && ! $event->isOperationallyReadOnly() && $user->can('viewModule', [$event, 'budget']);
    }

    public function update(User $user, Income $income): bool
    {
        return $user->hasPermission('finance.create') && $income->status === 'draft' && ! $income->event->isOperationallyReadOnly() && $this->view($user, $income);
    }

    public function post(User $user, Income $income): bool
    {
        return $user->hasPermission('finance.approve') && $income->status === 'draft' && ! $income->event->isOperationallyReadOnly() && $this->view($user, $income);
    }

    public function void(User $user, Income $income): bool
    {
        return $user->hasPermission('finance.void') && in_array($income->status, ['draft', 'posted'], true) && ! $income->event->isOperationallyReadOnly() && $this->view($user, $income);
    }
}
