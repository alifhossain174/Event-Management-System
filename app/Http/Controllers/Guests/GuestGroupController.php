<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\GuestGroupRequest;
use App\Models\Event;
use App\Services\GuestService;
use Illuminate\Http\RedirectResponse;

final class GuestGroupController extends Controller
{
    public function store(GuestGroupRequest $request, Event $event, GuestService $service): RedirectResponse
    {
        $service->createGroup($event, $request->validated(), $request->user());

        return back()->with('status', 'Guest group created.');
    }
}
