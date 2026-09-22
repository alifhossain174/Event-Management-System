<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\TicketQrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublicTicketController extends Controller
{
    public function catalogue(Event $event): View
    {
        $this->guardPublished($event);
        $types = $event->ticketTypes()->where('is_public', true)->whereIn('status', ['on_sale', 'sold_out'])
            ->whereNull('archived_at')->withCount(['tickets as active_tickets_count' => fn ($query) => $query->whereIn('status', ['issued', 'used'])])
            ->orderBy('display_order')->orderBy('name')->get();

        return view('public.tickets.catalogue', ['event' => $event, 'types' => $types]);
    }

    public function entry(Request $request, string $token): View
    {
        $ticket = $this->ticket($token);
        $this->guardPublished($ticket->event);

        return view('public.tickets.entry', ['ticket' => $ticket]);
    }

    public function qr(string $token, TicketQrCodeService $qr): Response
    {
        $ticket = $this->ticket($token);
        $this->guardPublished($ticket->event);

        return response($qr->svg(route('public.tickets.entry', ['token' => $token])), 200, [
            'Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ticket(string $token): Ticket
    {
        abort_if(strlen($token) < 32 || strlen($token) > 255, 404);

        return Ticket::query()->where('token_hash', hash('sha256', $token))->with(['event', 'type', 'order'])->firstOrFail();
    }

    private function guardPublished(Event $event): void
    {
        abort_unless($event->ticketing_published_at && $event->moduleSettings()->where('module_key', 'ticketing')->where('is_enabled', true)->exists(), 404);
    }
}
