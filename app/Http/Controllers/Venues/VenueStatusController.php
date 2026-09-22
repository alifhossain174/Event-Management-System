<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\VenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class VenueStatusController extends Controller
{
    public function __invoke(Request $request, Venue $venue, VenueService $service): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['archive', 'reactivate'])], 'reason' => ['nullable', 'required_if:action,archive', 'string', 'max:2000']]);
        Gate::authorize($data['action'], $venue);
        $service->changeStatus($venue, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', $data['action'] === 'archive' ? 'Venue archived; historical allocations remain available.' : 'Venue reactivated.');
    }
}
