<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ModuleDefinition;
use App\Models\Venue;
use App\Services\BranchScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventModuleWorkspaceController extends Controller
{
    public function __invoke(Request $request, Event $event, string $moduleKey, BranchScope $branches): View|RedirectResponse
    {
        Gate::authorize('viewModule', [$event, $moduleKey]);

        if ($moduleKey === 'venue') {
            $event->load(['client', 'category', 'venueAllocations.venue', 'venueAllocations.space', 'venueAllocations.statusHistory.actor']);
            $venues = $branches->apply(Venue::query()->where('status', 'active'), $request->user())
                ->with(['spaces' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('name')->get();

            return view('events.venue', compact('event', 'venues'));
        }

        if ($moduleKey === 'vendors') {
            return redirect()->route('events.vendors.index', $event);
        }

        if ($moduleKey === 'staff') {
            return redirect()->route('events.staff.index', $event);
        }

        if ($moduleKey === 'tasks') {
            return redirect()->route('events.tasks.index', $event);
        }

        if ($moduleKey === 'guests') {
            return redirect()->route('events.guests.index', $event);
        }

        if ($moduleKey === 'registration') {
            return redirect()->route('events.registrations.index', $event);
        }

        if ($moduleKey === 'ticketing') {
            return redirect()->route('events.tickets.index', $event);
        }

        if ($moduleKey === 'budget') {
            return redirect()->route('events.budget.index', $event);
        }

        if ($moduleKey === 'payments') {
            return redirect()->route('events.payments.index', $event);
        }

        if ($moduleKey === 'invoices') {
            return redirect()->route('events.invoices.index', $event);
        }

        if ($moduleKey === 'communications') {
            return redirect()->route('events.communications.index', $event);
        }

        return view('events.module-workspace', [
            'event' => $event->load(['client', 'category']),
            'moduleDefinition' => ModuleDefinition::query()->where('key', $moduleKey)->firstOrFail(),
        ]);
    }
}
