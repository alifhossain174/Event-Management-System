<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeMasterRecordStatusRequest;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;

final class VendorStatusController extends Controller
{
    public function __invoke(ChangeMasterRecordStatusRequest $request, Vendor $vendor, VendorService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->changeStatus($vendor, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', $data['action'] === 'archive' ? 'Vendor archived; history was preserved.' : 'Vendor reactivated.');
    }
}
