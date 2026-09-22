<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\VendorServiceAreaRequest;
use App\Models\Vendor;
use App\Models\VendorServiceArea;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class VendorServiceAreaController extends Controller
{
    public function store(VendorServiceAreaRequest $request, Vendor $vendor, VendorService $service): RedirectResponse
    {
        $service->addServiceArea($vendor, $request->validated(), $request->user());

        return back()->with('status', 'Service area added.');
    }

    public function destroy(Vendor $vendor, VendorServiceArea $area, VendorService $service): RedirectResponse
    {
        abort_unless($area->vendor_id === $vendor->id, 404);
        Gate::authorize('update', $vendor);
        $service->removeServiceArea($area, request()->user());

        return back()->with('status', 'Service area archived.');
    }
}
