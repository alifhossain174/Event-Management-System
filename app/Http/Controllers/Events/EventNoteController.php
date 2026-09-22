<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventNoteRequest;
use App\Models\Event;
use App\Services\EventNoteService;
use Illuminate\Http\RedirectResponse;

final class EventNoteController extends Controller
{
    public function store(StoreEventNoteRequest $request, Event $event, EventNoteService $notes): RedirectResponse
    {
        $notes->create(
            $event,
            $request->validated('body'),
            $request->boolean('is_pinned'),
            $request->boolean('include_in_duplicate'),
            $request->user(),
        );

        return back()->with('status', 'Event note added.');
    }
}
