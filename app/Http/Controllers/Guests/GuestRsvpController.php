<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\RsvpRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestService;
use Illuminate\Http\RedirectResponse;

final class GuestRsvpController extends Controller
{
    public function store(RsvpRequest $request, Event $event, Guest $guest, GuestService $service): RedirectResponse
    {
        abort_unless($guest->event_id === $event->getKey(), 404);
        $service->saveRsvp($guest, $request->validated(), $request->user());

        return back()->with('status', 'RSVP recorded.');
    }
}
