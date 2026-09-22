<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeMasterRecordStatusRequest;
use App\Models\StaffProfile;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;

final class StaffStatusController extends Controller
{
    public function __invoke(ChangeMasterRecordStatusRequest $request, StaffProfile $staff, StaffService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->changeStatus($staff, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', $data['action'] === 'archive' ? 'Staff profile archived; history was preserved.' : 'Staff profile reactivated.');
    }
}
