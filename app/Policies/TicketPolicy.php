<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;

final class TicketPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->hasPermission('tickets.view') && $user->can('viewModule', [$event, 'ticketing']);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user, $ticket->event);
    }

    public function configure(User $user, Event $event): bool
    {
        return $user->hasPermission('tickets.configure') && ! $event->isOperationallyReadOnly() && $this->viewAny($user, $event);
    }

    public function issue(User $user, Event $event): bool
    {
        return $user->hasPermission('tickets.issue') && ! $event->isOperationallyReadOnly() && $this->viewAny($user, $event);
    }

    public function refund(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.refund') && $ticket->status === 'issued' && $this->view($user, $ticket);
    }

    public function validate(User $user, Event $event): bool
    {
        return $user->hasPermission('tickets.validate') && $this->viewAny($user, $event);
    }

    public function email(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.email') && $this->view($user, $ticket);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->hasPermission('tickets.publish') && $this->viewAny($user, $event);
    }
}
