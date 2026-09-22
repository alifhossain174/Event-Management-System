<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\VendorContactRequest;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class VendorContactController extends Controller
{
    public function store(VendorContactRequest $request, Vendor $vendor, VendorService $service): RedirectResponse
    {
        $service->addContact($vendor, $request->validated(), $request->user());

        return back()->with('status', 'Vendor contact added.');
    }

    public function destroy(Vendor $vendor, VendorContact $contact, VendorService $service): RedirectResponse
    {
        abort_unless($contact->vendor_id === $vendor->id, 404);
        Gate::authorize('update', $vendor);
        $service->removeContact($contact, request()->user());

        return back()->with('status', 'Vendor contact archived.');
    }
}
