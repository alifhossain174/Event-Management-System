<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Models\EventVenueAllocation;
use App\Models\Venue;
use App\Services\BranchScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VenueAvailabilityController extends Controller
{
    public function __invoke(Request $request, BranchScope $branches): View
    {
        Gate::authorize('viewAny', Venue::class);
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from'],
            'venue' => ['nullable', 'integer', 'exists:venues,id'], 'view' => ['nullable', 'in:list,calendar'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $viewMode = $filters['view'] ?? 'list';
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'));
        $from = $viewMode === 'calendar' ? $month->startOfMonth()->toDateString() : ($filters['from'] ?? null);
        $until = $viewMode === 'calendar' ? $month->endOfMonth()->toDateString() : ($filters['until'] ?? null);
        $visibleVenueIds = $branches->apply(Venue::query(), $request->user())->select('id');
        $allocations = EventVenueAllocation::query()->with(['event.client', 'venue', 'space'])
            ->whereIn('event_venue_allocations.venue_id', $visibleVenueIds)
            ->whereIn('event_venue_allocations.status', EventVenueAllocation::ACTIVE_STATUSES)
            ->whereHas('event', fn ($query) => $query->where('status', '!=', 'cancelled')
                ->when($from, fn ($query, $date) => $query->where('ends_at', '>=', $date))
                ->when($until, fn ($query, $date) => $query->where('starts_at', '<=', $date.' 23:59:59')))
            ->when($filters['venue'] ?? null, fn ($query, $id) => $query->where('event_venue_allocations.venue_id', $id))
            ->join('events', 'event_venue_allocations.event_id', '=', 'events.id')->orderBy('events.starts_at')
            ->select('event_venue_allocations.*')->paginate($viewMode === 'calendar' ? 200 : 30)->withQueryString();
        $venues = $branches->apply(Venue::query()->where('status', 'active'), $request->user())->orderBy('name')->get();
        $gridStart = $month->startOfMonth()->startOfWeek();
        $calendarWeeks = collect(range(0, 5))->map(fn (int $week) => collect(range(0, 6))->map(function (int $day) use ($gridStart, $week, $allocations) {
            $date = $gridStart->addDays(($week * 7) + $day);

            return [
                'date' => $date,
                'allocations' => $allocations->getCollection()->filter(fn (EventVenueAllocation $allocation) => $allocation->event->starts_at->lt($date->endOfDay())
                    && $allocation->event->ends_at->gt($date->startOfDay())),
            ];
        }));

        return view('venues.availability', compact('allocations', 'venues', 'filters', 'viewMode', 'month', 'calendarWeeks'));
    }
}
