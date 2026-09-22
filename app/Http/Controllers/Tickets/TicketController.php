<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Services\TicketQrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class TicketController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        Gate::authorize('viewAny', [Ticket::class, $event]);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:issued,cancelled,partially_refunded,refunded'], 'type_id' => ['nullable', 'integer']]);
        $orders = TicketOrder::query()->where('event_id', $event->getKey())->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['type_id'] ?? null, fn ($query, $value) => $query->where('ticket_type_id', $value))
            ->with(['type', 'tickets.validation', 'promoCode', 'payment'])->orderByDesc('issued_at')->paginate(25)->withQueryString();

        return view('events.tickets.index', [
            'event' => $event, 'orders' => $orders, 'filters' => $filters,
            'types' => $event->ticketTypes()->withCount(['tickets as active_tickets_count' => fn ($query) => $query->whereIn('status', ['issued', 'used'])])->orderBy('display_order')->orderBy('name')->get(),
            'promos' => $event->promoCodes()->orderBy('code')->get(),
            'payments' => $request->user()->hasPermission('payments.view') ? $event->payments()->where('status', 'posted')->get() : collect(),
            'registrations' => $event->registrations()->where('status', 'approved')->orderBy('registrant_name')->limit(200)->get(),
        ]);
    }

    public function show(Event $event, TicketOrder $ticketOrder): View
    {
        $this->guardOrder($event, $ticketOrder);
        $ticketOrder->load(['event.client', 'type', 'tickets.validation.validatedBy', 'tickets.refund.financialRefund', 'promoCode', 'payment', 'outboundMessages.deliveryLogs']);
        abort_unless(Gate::allows('view', $ticketOrder->tickets->first()), 403);

        return view('events.tickets.show', ['event' => $event, 'order' => $ticketOrder]);
    }

    public function print(Event $event, TicketOrder $ticketOrder): View
    {
        $this->guardOrder($event, $ticketOrder);
        $ticketOrder->load(['event', 'type', 'tickets']);
        abort_unless($ticketOrder->tickets->every(fn (Ticket $ticket) => Gate::allows('view', $ticket)), 403);

        return view('events.tickets.print', ['event' => $event, 'order' => $ticketOrder]);
    }

    public function qr(Event $event, Ticket $ticket, TicketQrCodeService $qr): Response
    {
        abort_unless($ticket->event_id === $event->getKey(), 404);
        Gate::authorize('view', $ticket);
        $payload = route('public.tickets.entry', ['token' => $ticket->plainToken()]);

        return response($qr->svg($payload), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store']);
    }

    private function guardOrder(Event $event, TicketOrder $order): void
    {
        abort_unless($order->event_id === $event->getKey(), 404);
    }
}
