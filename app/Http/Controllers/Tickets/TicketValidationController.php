<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\ValidateTicketRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class TicketValidationController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('validate', [Ticket::class, $event]);

        return view('events.tickets.validate', ['event' => $event, 'result' => null]);
    }

    public function store(ValidateTicketRequest $request, Event $event, TicketService $service): View
    {
        $result = $service->validate($event, $request->string('token')->toString(), $request->user(), $request->input('method', 'qr'));

        return view('events.tickets.validate', ['event' => $event, 'result' => $result]);
    }
}
