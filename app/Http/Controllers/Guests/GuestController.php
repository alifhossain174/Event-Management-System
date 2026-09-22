<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\GuestRequest;
use App\Http\Requests\Guests\GuestStatusRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Services\GuestCapacityService;
use App\Services\GuestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class GuestController extends Controller
{
    public function index(Request $request, Event $event, GuestCapacityService $capacity): View
    {
        Gate::authorize('viewAny', [Guest::class, $event]);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'rsvp_status' => ['nullable', 'in:pending,accepted,declined,tentative'],
            'invitation_status' => ['nullable', 'in:not_invited,issued,sent,revoked'], 'vip' => ['nullable', 'in:0,1'],
            'group_id' => ['nullable', 'integer'], 'archived' => ['nullable', 'in:0,1'],
        ]);
        $guests = Guest::query()->where('event_id', $event->getKey())
            ->search($filters['q'] ?? null)
            ->when(! ($filters['archived'] ?? false), fn ($query) => $query->whereNull('archived_at'))
            ->when(($filters['archived'] ?? null) === '1', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($filters['rsvp_status'] ?? null, fn ($query, $value) => $query->where('rsvp_status', $value))
            ->when($filters['invitation_status'] ?? null, fn ($query, $value) => $query->where('invitation_status', $value))
            ->when(isset($filters['vip']), fn ($query) => $query->where('is_vip', $filters['vip'] === '1'))
            ->when($filters['group_id'] ?? null, fn ($query, $value) => $query->whereHas('groupMembership', fn ($members) => $members->where('guest_group_id', $value)))
            ->with(['groupMembership.group', 'rsvp', 'seatAssignment', 'checkIn'])
            ->orderBy('display_name')->paginate(25)->withQueryString();
        $groups = GuestGroup::query()->where('event_id', $event->getKey())->whereNull('archived_at')->withCount('members')->orderBy('name')->get();

        return view('events.guests.index', ['event' => $event, 'guests' => $guests, 'groups' => $groups, 'filters' => $filters, 'capacity' => $capacity->summary($event)]);
    }

    public function create(Event $event): View
    {
        Gate::authorize('create', [Guest::class, $event]);

        return view('events.guests.form', ['event' => $event, 'guest' => new Guest, 'groups' => $this->groups($event)]);
    }

    public function store(GuestRequest $request, Event $event, GuestService $service): RedirectResponse
    {
        $guest = $service->create($event, $request->validated(), $request->user());

        return redirect()->route('events.guests.show', [$event, $guest])->with('status', 'Guest created.');
    }

    public function show(Request $request, Event $event, Guest $guest, GuestCapacityService $capacity): View
    {
        $this->guardEvent($event, $guest);
        Gate::authorize('view', $guest);
        $canViewPrivateNotes = $request->user()->can('viewPrivateNotes', $guest);
        $guest->load([
            'groupMembership.group', 'invitations.statusHistory.actor', 'rsvp.statusHistory.actor',
            'seatAssignment', 'checkIn.operator',
            'notes' => fn ($query) => $query->when(! $canViewPrivateNotes, fn ($notes) => $notes->where('visibility', 'operations'))->with('author'),
        ]);

        return view('events.guests.show', ['event' => $event, 'guest' => $guest, 'groups' => $this->groups($event), 'capacity' => $capacity->summary($event)]);
    }

    public function edit(Event $event, Guest $guest): View
    {
        $this->guardEvent($event, $guest);
        Gate::authorize('update', $guest);
        $guest->load('groupMembership');

        return view('events.guests.form', ['event' => $event, 'guest' => $guest, 'groups' => $this->groups($event)]);
    }

    public function update(GuestRequest $request, Event $event, Guest $guest, GuestService $service): RedirectResponse
    {
        $this->guardEvent($event, $guest);
        $service->update($guest, $request->validated(), $request->user());

        return redirect()->route('events.guests.show', [$event, $guest])->with('status', 'Guest updated.');
    }

    public function status(GuestStatusRequest $request, Event $event, Guest $guest, GuestService $service): RedirectResponse
    {
        $this->guardEvent($event, $guest);
        if ($request->validated('action') === 'archive') {
            $service->archive($guest, $request->user(), $request->validated('reason'));
        } else {
            $service->reactivate($guest, $request->user(), $request->validated('reason'));
        }

        return back()->with('status', 'Guest status updated; history was preserved.');
    }

    private function groups(Event $event): Collection
    {
        return GuestGroup::query()->where('event_id', $event->getKey())->whereNull('archived_at')->orderBy('name')->get();
    }

    private function guardEvent(Event $event, Guest $guest): void
    {
        abort_unless($guest->event_id === $event->getKey(), 404);
    }
}
