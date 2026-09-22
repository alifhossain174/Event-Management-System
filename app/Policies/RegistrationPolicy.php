<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

final class RegistrationPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->hasPermission('registrations.view') && $user->can('viewModule', [$event, 'registration']);
    }

    public function view(User $user, Registration $registration): bool
    {
        return $this->viewAny($user, $registration->event);
    }

    public function review(User $user, Registration $registration): bool
    {
        return $user->hasPermission('registrations.approve') && ! $registration->event->isOperationallyReadOnly() && $this->view($user, $registration);
    }

    public function export(User $user, Event $event): bool
    {
        return $user->hasPermission('registrations.export') && $this->viewAny($user, $event);
    }

    public function convertGuest(User $user, Registration $registration): bool
    {
        return $user->hasPermission('registrations.convert-guest')
            && $user->hasPermission('guests.create')
            && ! $registration->event->isOperationallyReadOnly()
            && $this->view($user, $registration);
    }
}
