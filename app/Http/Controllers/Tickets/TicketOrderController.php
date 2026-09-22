<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\IssueTicketOrderRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\CommunicationService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TicketOrderController extends Controller
{
    public function store(IssueTicketOrderRequest $request, Event $event, TicketService $service): RedirectResponse
    {
        $type = TicketType::query()->findOrFail($request->integer('ticket_type_id'));
        $order = $service->issue($event, $type, $request->validated(), $request->user());

        return redirect()->route('events.ticket-orders.show', [$event, $order])->with('status', 'Ticket Order issued.');
    }

    public function cancel(Request $request, Event $event, TicketOrder $ticketOrder, TicketService $service): RedirectResponse
    {
        abort_unless($ticketOrder->event_id === $event->getKey(), 404);
        Gate::authorize('issue', [Ticket::class, $event]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->cancelOrder($event, $ticketOrder, $data['reason'], $request->user());

        return back()->with('status', 'Ticket Order cancelled.');
    }

    public function email(Request $request, Event $event, TicketOrder $ticketOrder, CommunicationService $communications): RedirectResponse
    {
        abort_unless($ticketOrder->event_id === $event->getKey(), 404);
        $ticketOrder->load('tickets');
        $ticket = $ticketOrder->tickets->firstOrFail();
        Gate::authorize('email', $ticket);
        if (! $event->ticketing_published_at || ! $event->ticketing_public_slug) {
            throw ValidationException::withMessages(['email' => 'Publish the Event Ticket catalogue before emailing public Ticket links.']);
        }
        $data = $request->validate(['recipient_address' => ['required', 'email:rfc', 'max:320'], 'recipient_name' => ['nullable', 'string', 'max:191']]);
        $message = $communications->compose($event, [
            'ticket_order_id' => $ticketOrder->getKey(), 'channel' => 'email', 'category' => 'operational',
            'subject' => 'Ticket order for '.$event->name,
            'body' => "Ticket order {$ticketOrder->reference_number} was issued for {$ticketOrder->quantity} admission(s). Contact the organizer for secure delivery of the printable admission credential.",
            'recipient_address' => $data['recipient_address'], 'recipient_name' => $data['recipient_name'] ?? $ticketOrder->attendee_name,
            'idempotency_key' => 'ticket-order-email:'.$ticketOrder->getKey().':'.Str::uuid(),
        ], $request->user());

        return back()->with('status', $message->status === 'sent' ? 'Ticket email sent.' : 'Ticket email attempt failed and was logged.');
    }
}
