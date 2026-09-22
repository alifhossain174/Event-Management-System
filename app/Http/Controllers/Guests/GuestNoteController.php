<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\GuestNoteRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestService;
use Illuminate\Http\RedirectResponse;

final class GuestNoteController extends Controller
{
    public function store(GuestNoteRequest $request, Event $event, Guest $guest, GuestService $service): RedirectResponse
    {
        abort_unless($guest->event_id === $event->getKey(), 404);
        $service->addNote($guest, $request->validated(), $request->user());

        return back()->with('status', 'Guest note added.');
    }
}
