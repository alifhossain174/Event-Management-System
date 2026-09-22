<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class AuthenticationService
{
    public function attempt(string $email, string $password, bool $remember, Request $request): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $user = User::withTrashed()->where('email', $normalizedEmail)->first();
        $failureReason = null;

        if (! $user || ! Hash::check($password, $user->password)) {
            $failureReason = 'invalid_credentials';
        } elseif ($user->trashed()) {
            $failureReason = 'archived';
        } elseif (! $user->is_active) {
            $failureReason = 'inactive';
        }

        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'email' => $normalizedEmail,
            'successful' => $failureReason === null,
            'failure_reason' => $failureReason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempted_at' => now(),
        ]);

        if ($failureReason !== null) {
            return false;
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        Auth::login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();

        return true;
    }
}
