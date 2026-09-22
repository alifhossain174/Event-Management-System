<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\CancelBookingRequest;
use App\Http\Requests\Bookings\ConfirmBookingRequest;
use App\Http\Requests\Bookings\RescheduleBookingRequest;
use App\Http\Requests\Bookings\ReviewBookingRequest;
use App\Http\Requests\Bookings\WaitlistBookingRequest;
use App\Models\Booking;
use App\Services\BookingWorkflowService;
use Illuminate\Http\RedirectResponse;

final class BookingWorkflowController extends Controller
{
    public function review(ReviewBookingRequest $request, Booking $booking, BookingWorkflowService $workflow): RedirectResponse
    {
        $workflow->review($booking, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Booking moved to review.');
    }

    public function confirm(ConfirmBookingRequest $request, Booking $booking, BookingWorkflowService $workflow): RedirectResponse
    {
        $workflow->confirm($booking, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Booking approved and confirmed.');
    }

    public function waitlist(WaitlistBookingRequest $request, Booking $booking, BookingWorkflowService $workflow): RedirectResponse
    {
        $workflow->waitlist($booking, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Booking added to the waitlist.');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, BookingWorkflowService $workflow): RedirectResponse
    {
        $workflow->cancel($booking, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Booking cancelled. Financial records, when available, are preserved for review.');
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking, BookingWorkflowService $workflow): RedirectResponse
    {
        $workflow->reschedule(
            $booking,
            $request->startsAt(),
            $request->endsAt(),
            $request->validated('timezone'),
            $request->validated('venue_preference'),
            $request->user(),
            $request->validated('reason'),
            $request->boolean('override_conflicts'),
        );

        return back()->with('status', 'Booking schedule updated.');
    }
}
