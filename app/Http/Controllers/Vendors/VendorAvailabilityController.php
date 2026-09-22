<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\VendorAvailabilityRequest;
use App\Models\Vendor;
use App\Models\VendorAvailability;
use App\Services\VendorAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VendorAvailabilityController extends Controller
{
    public function store(VendorAvailabilityRequest $request, Vendor $vendor, VendorAvailabilityService $service): RedirectResponse
    {
        $service->add($vendor, $request->availabilityAttributes(), $request->user());

        return back()->with('status', 'Vendor availability rule added.');
    }

    public function destroy(Request $request, Vendor $vendor, VendorAvailability $availability, VendorAvailabilityService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('vendors.manage-availability'), 403);
        Gate::authorize('view', $vendor);
        $service->archive($vendor, $availability, $request->user());

        return back()->with('status', 'Availability rule archived.');
    }
}
