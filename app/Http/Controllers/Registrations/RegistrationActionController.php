<?php

namespace App\Http\Controllers\Registrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\ConvertRegistrationGuestRequest;
use App\Http\Requests\Registrations\ReviewRegistrationRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Services\RegistrationGuestService;
use App\Services\RegistrationWorkflowService;
use Illuminate\Http\RedirectResponse;

final class RegistrationActionController extends Controller
{
    public function review(ReviewRegistrationRequest $request, Event $event, Registration $registration, RegistrationWorkflowService $service): RedirectResponse
    {
        $this->guard($event, $registration);
        $service->transition($registration, $request->validated('status'), $request->validated(), $request->user());

        return back()->with('status', 'Registration review recorded.');
    }

    public function convertGuest(ConvertRegistrationGuestRequest $request, Event $event, Registration $registration, RegistrationGuestService $service): RedirectResponse
    {
        $this->guard($event, $registration);
        $guest = $service->convert($registration, $request->user(), $request->validated('existing_guest_id'));

        return redirect()->route('events.guests.show', [$event, $guest])->with('status', 'Registration linked to Guest.');
    }

    private function guard(Event $event, Registration $registration): void
    {
        abort_unless($registration->event_id === $event->getKey(), 404);
    }
}
