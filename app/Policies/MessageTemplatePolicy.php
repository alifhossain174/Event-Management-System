<?php

namespace App\Policies;

use App\Models\MessageTemplate;
use App\Models\User;

final class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('communications.configure');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('communications.configure');
    }

    public function update(User $user, MessageTemplate $template): bool
    {
        return $user->hasPermission('communications.configure');
    }
}
