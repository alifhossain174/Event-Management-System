<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class TicketPublicationController extends Controller
{
    public function __invoke(Request $request, Event $event, TicketService $service): RedirectResponse
    {
        Gate::authorize('publish', [Ticket::class, $event]);
        $data = $request->validate(['publish' => ['required', 'boolean']]);
        $service->publish($event, (bool) $data['publish'], $request->user());

        return back()->with('status', $data['publish'] ? 'Public Ticket catalogue published.' : 'Public Ticket catalogue unpublished.');
    }
}
