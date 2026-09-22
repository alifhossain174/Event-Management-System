<?php

namespace App\Policies;

use App\Models\NotificationRecipient;
use App\Models\User;

final class NotificationRecipientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('notifications.view');
    }

    public function update(User $user, NotificationRecipient $recipient): bool
    {
        return $user->hasPermission('notifications.view') && $recipient->user_id === $user->getKey();
    }
}
