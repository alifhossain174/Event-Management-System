<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkMasterRecordUserRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;

final class VendorUserLinkController extends Controller
{
    public function __invoke(LinkMasterRecordUserRequest $request, Vendor $vendor, VendorService $service): RedirectResponse
    {
        $user = $request->validated('user_id') ? User::query()->findOrFail($request->validated('user_id')) : null;
        $service->linkUser($vendor, $user, $request->user());

        return back()->with('status', $user ? 'Vendor account linked.' : 'Vendor account link removed.');
    }
}
