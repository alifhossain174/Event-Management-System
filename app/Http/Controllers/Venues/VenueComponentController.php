<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venues\SeatingPlanRequest;
use App\Http\Requests\Venues\VenueFacilityRequest;
use App\Http\Requests\Venues\VenueMediaRequest;
use App\Http\Requests\Venues\VenueRateRequest;
use App\Http\Requests\Venues\VenueSpaceRequest;
use App\Models\SeatingPlan;
use App\Models\Venue;
use App\Models\VenueFacility;
use App\Models\VenueRate;
use App\Models\VenueSpace;
use App\Services\VenueComponentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VenueComponentController extends Controller
{
    public function space(VenueSpaceRequest $request, Venue $venue, VenueComponentService $service): RedirectResponse
    {
        $service->addSpace($venue, $request->validated(), $request->user());

        return back()->with('status', 'Venue space added.');
    }

    public function facility(VenueFacilityRequest $request, Venue $venue, VenueComponentService $service): RedirectResponse
    {
        $service->addFacility($venue, $request->validated(), $request->user());

        return back()->with('status', 'Facility added.');
    }

    public function rate(VenueRateRequest $request, Venue $venue, VenueComponentService $service): RedirectResponse
    {
        $service->addRate($venue, $request->validated(), $request->user());

        return back()->with('status', 'Optional rate guidance added; event allocations may still use a quoted price.');
    }

    public function seatingPlan(SeatingPlanRequest $request, Venue $venue, VenueComponentService $service): RedirectResponse
    {
        $service->addSeatingPlan($venue, $request->validated(), $request->user());

        return back()->with('status', 'Seating plan metadata added.');
    }

    public function media(VenueMediaRequest $request, Venue $venue, VenueComponentService $service): RedirectResponse
    {
        $service->addMedia($venue, $request->file('file'), $request->validated(), $request->user());

        return back()->with('status', 'Venue image stored privately.');
    }

    public function destroySpace(Request $request, Venue $venue, VenueSpace $space, VenueComponentService $service): RedirectResponse
    {
        return $this->archive($request, $venue, $space, $service);
    }

    public function destroyFacility(Request $request, Venue $venue, VenueFacility $facility, VenueComponentService $service): RedirectResponse
    {
        return $this->archive($request, $venue, $facility, $service);
    }

    public function destroyRate(Request $request, Venue $venue, VenueRate $rate, VenueComponentService $service): RedirectResponse
    {
        return $this->archive($request, $venue, $rate, $service);
    }

    public function destroySeatingPlan(Request $request, Venue $venue, SeatingPlan $seatingPlan, VenueComponentService $service): RedirectResponse
    {
        return $this->archive($request, $venue, $seatingPlan, $service);
    }

    private function archive(Request $request, Venue $venue, object $component, VenueComponentService $service): RedirectResponse
    {
        Gate::authorize('update', $venue);
        $service->archive($venue, $component, $request->user());

        return back()->with('status', 'Venue component archived.');
    }
}
