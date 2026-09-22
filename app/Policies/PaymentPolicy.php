<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Payment;
use App\Models\User;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.view')
            && $user->can('view', $payment->event)
            && $user->can('viewModule', [$payment->event, 'payments']);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('payments.create')
            && ! $event->archived_at
            && $event->client_id !== null
            && $user->can('viewModule', [$event, 'payments']);
    }

    public function schedule(User $user, Event $event): bool
    {
        return $user->hasPermission('payments.create')
            && ! $event->isOperationallyReadOnly()
            && $event->client_id !== null
            && $user->can('viewModule', [$event, 'payments']);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.refund')
            && ! $payment->event->archived_at
            && $this->view($user, $payment);
    }
}
