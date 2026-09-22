<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\RegistrationForm;
use App\Models\User;

final class RegistrationFormPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->hasPermission('registrations.view') && $user->can('viewModule', [$event, 'registration']);
    }

    public function view(User $user, RegistrationForm $form): bool
    {
        return $this->viewAny($user, $form->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('registrations.configure') && ! $event->isOperationallyReadOnly() && $this->viewAny($user, $event);
    }

    public function update(User $user, RegistrationForm $form): bool
    {
        return $this->configure($user, $form);
    }

    public function configure(User $user, RegistrationForm $form): bool
    {
        return $user->hasPermission('registrations.configure') && ! $form->event->isOperationallyReadOnly() && $this->view($user, $form);
    }

    public function submitOffline(User $user, RegistrationForm $form): bool
    {
        return $user->hasPermission('registrations.create') && ! $form->event->isOperationallyReadOnly() && $this->view($user, $form);
    }
}
