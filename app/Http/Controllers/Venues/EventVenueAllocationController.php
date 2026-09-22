<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venues\CancelVenueAllocationRequest;
use App\Http\Requests\Venues\EventVenueAllocationRequest;
use App\Models\Event;
use App\Models\EventVenueAllocation;
use App\Models\Venue;
use App\Services\AvailabilityService;
use App\Services\BranchScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventVenueAllocationController extends Controller
{
    public function index(Request $request, Event $event, BranchScope $branches): View
    {
        Gate::authorize('viewModule', [$event, 'venue']);
        $event->load(['client', 'category', 'venueAllocations.venue', 'venueAllocations.space', 'venueAllocations.statusHistory.actor']);
        $venues = $branches->apply(Venue::query()->where('status', 'active'), $request->user())->with(['spaces' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->get();

        return view('events.venue', compact('event', 'venues'));
    }

    public function store(EventVenueAllocationRequest $request, Event $event, AvailabilityService $availability): RedirectResponse
    {
        $allocation = $availability->allocate($event, $request->validated(), $request->user());
        $message = $allocation->capacity_warning
            ? 'Venue allocated. Warning: expected guests exceed the selected capacity.'
            : 'Venue allocated.';

        return back()->with($allocation->capacity_warning ? 'warning' : 'status', $message);
    }

    public function cancel(CancelVenueAllocationRequest $request, Event $event, EventVenueAllocation $allocation, AvailabilityService $availability): RedirectResponse
    {
        abort_unless($allocation->event_id === $event->getKey(), 404);
        $availability->cancel($allocation, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Venue allocation cancelled; history was preserved.');
    }
}
