<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\TransitionEventStatusRequest;
use App\Models\Event;
use App\Services\EventLifecycleService;
use Illuminate\Http\RedirectResponse;

final class EventStatusController extends Controller
{
    public function __invoke(TransitionEventStatusRequest $request, Event $event, EventLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validated();
        $lifecycle->transition($event, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Event status changed to '.str($data['status'])->headline().'.');
    }
}
