<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\GuestCheckInRequest;
use App\Models\Event;
use App\Services\GuestCheckInService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class GuestCheckInController extends Controller
{
    public function index(Request $request, Event $event, GuestCheckInService $service): View
    {
        Gate::authorize('viewModule', [$event, 'guests']);
        abort_unless($request->user()->hasPermission('guests.check-in'), 403);
        $result = null;
        $error = null;
        if ($request->filled('token')) {
            $request->validate(['token' => ['required', 'string', 'size:43', 'regex:/^[A-Za-z0-9_-]+$/']]);
            try {
                $result = $service->lookup($event, $request->string('token')->toString());
            } catch (ValidationException $exception) {
                $error = $exception->validator->errors()->first('token');
            }
        }

        return view('events.guests.check-in', compact('event', 'result', 'error'));
    }

    public function store(GuestCheckInRequest $request, Event $event, GuestCheckInService $service): View
    {
        $result = $service->checkIn(
            $event,
            $request->validated('token'),
            $request->user(),
            $request->integer('party_size') ?: null,
            $request->validated('notes'),
        );
        $error = null;

        return view('events.guests.check-in', compact('event', 'result', 'error'));
    }
}
