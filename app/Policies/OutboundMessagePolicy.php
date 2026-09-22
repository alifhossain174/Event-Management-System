<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\OutboundMessage;
use App\Models\User;

final class OutboundMessagePolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->hasPermission('communications.view') && $user->can('viewModule', [$event, 'communications']);
    }

    public function view(User $user, OutboundMessage $message): bool
    {
        return $message->event !== null && $this->viewAny($user, $message->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('communications.send') && $this->viewAny($user, $event) && ! $event->archived_at;
    }

    public function retry(User $user, OutboundMessage $message): bool
    {
        return $message->status === 'failed' && $user->hasPermission('communications.retry') && $this->view($user, $message);
    }
}
