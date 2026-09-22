<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view') && $user->can('viewModule', [$invoice->event, 'invoices']);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->hasPermission('invoices.create') && $user->can('viewModule', [$event, 'invoices']) && ! $event->archived_at;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.update') && $invoice->status === 'draft' && $this->view($user, $invoice);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.issue') && $invoice->status === 'draft' && $this->view($user, $invoice);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.cancel') && in_array($invoice->status, ['draft', 'issued'], true) && $this->view($user, $invoice);
    }

    public function credit(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.credit') && in_array($invoice->status, ['issued', 'partially_paid', 'paid'], true) && $this->view($user, $invoice);
    }

    public function email(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.email') && $invoice->status !== 'draft' && $this->view($user, $invoice);
    }
}
