<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venues\VenueRequest;
use App\Models\Venue;
use App\Services\BranchScope;
use App\Services\VenueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VenueController extends Controller
{
    public function index(Request $request, BranchScope $branches): View
    {
        Gate::authorize('viewAny', Venue::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,archived'], 'type' => ['nullable', 'in:owned,third_party'], 'branch' => ['nullable', 'integer', 'exists:branches,id']]);
        $venues = $branches->apply(Venue::query(), $request->user())->with('branch')
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value), fn ($query) => $query->where('status', 'active'))
            ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->when($filters['branch'] ?? null, fn ($query, $value) => $query->where('branch_id', $value))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('venues.index', ['venues' => $venues, 'filters' => $filters, 'branches' => $branches->optionsFor($request->user())]);
    }

    public function create(Request $request, BranchScope $branches): View
    {
        Gate::authorize('create', Venue::class);

        return view('venues.create', ['branches' => $branches->optionsFor($request->user())]);
    }

    public function store(VenueRequest $request, VenueService $service): RedirectResponse
    {
        $venue = $service->create($request->validated(), $request->user());

        return redirect()->route('venues.show', $venue)->with('status', 'Venue created. Add spaces, facilities, rates, and protected images as needed.');
    }

    public function show(Venue $venue): View
    {
        Gate::authorize('view', $venue);
        $venue->load(['branch', 'spaces', 'facilities.space', 'rates.space', 'seatingPlans.space', 'media.document.currentVersion']);
        $allocations = $venue->allocations()->with(['event.client', 'space'])->latest()->paginate(15, ['*'], 'allocations');

        return view('venues.show', compact('venue', 'allocations'));
    }

    public function edit(Request $request, Venue $venue, BranchScope $branches): View
    {
        Gate::authorize('update', $venue);

        return view('venues.edit', ['venue' => $venue, 'branches' => $branches->optionsFor($request->user())]);
    }

    public function update(VenueRequest $request, Venue $venue, VenueService $service): RedirectResponse
    {
        $service->update($venue, $request->validated(), $request->user());

        return redirect()->route('venues.show', $venue)->with('status', 'Venue updated.');
    }
}
