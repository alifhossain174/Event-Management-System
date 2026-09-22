<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\CorrectEventRequest;
use App\Models\Event;
use App\Services\EventLifecycleService;
use Illuminate\Http\RedirectResponse;

final class EventCorrectionController extends Controller
{
    public function __invoke(CorrectEventRequest $request, Event $event, EventLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->reopenForCorrection($event, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Completed Event reopened in Planning for privileged correction.');
    }
}
