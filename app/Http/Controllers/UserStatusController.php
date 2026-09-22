<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\ChangeUserStatusRequest;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;

final class UserStatusController extends Controller
{
    public function __invoke(
        ChangeUserStatusRequest $request,
        User $user,
        UserManagementService $users,
    ): RedirectResponse {
        $validated = $request->validated();
        $users->setActive(
            $user,
            $validated['status'] === 'active',
            $request->user(),
            $validated['reason'] ?? null,
        );

        return back()->with('status', 'Account status updated.');
    }
}
