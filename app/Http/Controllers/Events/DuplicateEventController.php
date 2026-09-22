<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\DuplicateEventRequest;
use App\Models\Event;
use App\Services\EventDuplicationService;
use Illuminate\Http\RedirectResponse;

final class DuplicateEventController extends Controller
{
    public function __invoke(DuplicateEventRequest $request, Event $event, EventDuplicationService $events): RedirectResponse
    {
        $copy = $events->duplicate($event, $request->user(), $request->validated('name'), $request->boolean('copy_notes'));

        return redirect()->route('events.edit', $copy)->with('status', 'Independent Draft duplicated. Booking, transactions, attendance, scans, and prior history were not copied.');
    }
}
