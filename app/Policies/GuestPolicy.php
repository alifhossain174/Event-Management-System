<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;

final class GuestPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->hasPermission('guests.view') && $user->can('viewModule', [$event, 'guests']);
    }

    public function view(User $user, Guest $guest): bool
    {
        return $this->viewAny($user, $guest->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('guests.create') && ! $event->isOperationallyReadOnly() && $user->can('viewModule', [$event, 'guests']);
    }

    public function update(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.update') && ! $guest->event->isOperationallyReadOnly() && $this->view($user, $guest);
    }

    public function archive(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.delete') && ! $guest->event->isOperationallyReadOnly() && $this->view($user, $guest);
    }

    public function invite(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.invite') && ! $guest->event->isOperationallyReadOnly() && ! $guest->archived_at && $this->view($user, $guest);
    }

    public function viewInvitation(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.invite') && $this->view($user, $guest);
    }

    public function manageRsvp(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.manage-rsvp') && ! $guest->event->isOperationallyReadOnly() && ! $guest->archived_at && $this->view($user, $guest);
    }

    public function manageSeating(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.manage-seating') && ! $guest->event->isOperationallyReadOnly() && ! $guest->archived_at && $this->view($user, $guest);
    }

    public function addNote(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.add-notes') && ! $guest->event->isOperationallyReadOnly() && $this->view($user, $guest);
    }

    public function viewPrivateNotes(User $user, Guest $guest): bool
    {
        return $user->hasPermission('guests.view-private-notes') && $this->view($user, $guest);
    }
}
