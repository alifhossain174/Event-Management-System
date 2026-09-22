<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\TicketTypeRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class TicketTypeController extends Controller
{
    public function store(TicketTypeRequest $request, Event $event, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $type = $event->ticketTypes()->create($data + ['code' => strtoupper($data['code']), 'currency_code' => $event->currency_code, 'created_by_user_id' => $request->user()->getKey(), 'updated_by_user_id' => $request->user()->getKey()]);
        $audit->record('ticket.type_created', $type, [], $type->only(['event_id', 'name', 'code', 'category', 'quantity_total', 'price', 'status', 'is_public']), $request->user());

        return back()->with('status', 'Ticket Type created.');
    }

    public function update(TicketTypeRequest $request, Event $event, TicketType $ticketType, AuditService $audit): RedirectResponse
    {
        $this->guard($event, $ticketType);
        $data = $request->validated();
        if ((int) $data['quantity_total'] < $ticketType->activeIssuedCount()) {
            throw ValidationException::withMessages(['quantity_total' => 'Capacity cannot be lower than active issued and used Tickets.']);
        }
        $before = $ticketType->only(['name', 'code', 'category', 'quantity_total', 'price', 'sale_starts_at', 'sale_ends_at', 'status', 'is_public']);
        $ticketType->update($data + ['code' => strtoupper($data['code']), 'updated_by_user_id' => $request->user()->getKey()]);
        $audit->record('ticket.type_updated', $ticketType, $before, $ticketType->only(array_keys($before)), $request->user());

        return back()->with('status', 'Ticket Type updated.');
    }

    public function archive(Request $request, Event $event, TicketType $ticketType, AuditService $audit): RedirectResponse
    {
        $this->guard($event, $ticketType);
        Gate::authorize('configure', [Ticket::class, $event]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $before = $ticketType->status;
        $ticketType->update(['status' => 'archived', 'archived_at' => now(), 'archived_by_user_id' => $request->user()->getKey(), 'archive_reason' => $data['reason'], 'updated_by_user_id' => $request->user()->getKey()]);
        $audit->record('ticket.type_archived', $ticketType, ['status' => $before], ['status' => 'archived', 'reason' => $data['reason']], $request->user());

        return back()->with('status', 'Ticket Type archived; historical Tickets were preserved.');
    }

    private function guard(Event $event, TicketType $type): void
    {
        abort_unless($type->event_id === $event->getKey(), 404);
    }
}
