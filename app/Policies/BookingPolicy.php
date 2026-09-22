<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Services\BranchScope;

final class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.view')
            && app(BranchScope::class)->permits($user, $booking->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bookings.create');
    }

    public function review(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.update') && $this->view($user, $booking) && $booking->status === 'enquiry';
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.approve') && $this->view($user, $booking)
            && in_array($booking->status, ['enquiry', 'under_review', 'waitlisted'], true);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.cancel') && $this->view($user, $booking) && ! $booking->isTerminal();
    }

    public function reschedule(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.reschedule') && $this->view($user, $booking) && ! $booking->isTerminal();
    }

    public function waitlist(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.waitlist') && $this->view($user, $booking)
            && in_array($booking->status, ['enquiry', 'under_review'], true);
    }

    public function convert(User $user, Booking $booking): bool
    {
        return $user->hasPermission('bookings.convert') && $user->hasPermission('events.create')
            && $this->view($user, $booking) && in_array($booking->status, ['confirmed', 'converted'], true);
    }
}
