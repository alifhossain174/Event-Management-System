<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkMasterRecordUserRequest;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;

final class StaffUserLinkController extends Controller
{
    public function __invoke(LinkMasterRecordUserRequest $request, StaffProfile $staff, StaffService $service): RedirectResponse
    {
        $user = $request->validated('user_id') ? User::query()->findOrFail($request->validated('user_id')) : null;
        $service->linkUser($staff, $user, $request->user());

        return back()->with('status', $user ? 'Staff account linked.' : 'Staff account link removed.');
    }
}
