<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\RefundTicketRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;

final class TicketRefundController extends Controller
{
    public function __invoke(RefundTicketRequest $request, Event $event, Ticket $ticket, TicketService $service): RedirectResponse
    {
        abort_unless($ticket->event_id === $event->getKey(), 404);
        $service->refund($event, $ticket, $request->validated(), $request->user());

        return back()->with('status', 'Ticket refund recorded and Ticket invalidated.');
    }
}
