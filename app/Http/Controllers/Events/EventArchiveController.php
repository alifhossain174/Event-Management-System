<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ChangeEventArchiveStatusRequest;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;

final class EventArchiveController extends Controller
{
    public function __invoke(ChangeEventArchiveStatusRequest $request, Event $event, EventService $events): RedirectResponse
    {
        $data = $request->validated();
        if ($data['action'] === 'archive') {
            $events->archive($event, $request->user(), $data['reason']);
        } else {
            $events->reactivate($event, $request->user(), $data['reason'] ?? null);
        }

        return back()->with('status', $data['action'] === 'archive' ? 'Event archived without deleting history.' : 'Event reactivated.');
    }
}
