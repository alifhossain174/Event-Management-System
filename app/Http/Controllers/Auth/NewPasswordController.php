<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class NewPasswordController extends Controller
{
    public function create(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->string('email')->toString(),
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditService $audit): RedirectResponse
    {
        $credentials = $request->validated();
        $activeUserExists = User::query()
            ->where('email', mb_strtolower($credentials['email']))
            ->where('is_active', true)
            ->exists();

        $status = $activeUserExists
            ? Password::reset($credentials, function (User $user, string $password) use ($audit) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $audit->record('user.password_reset', $user);
            })
            : Password::INVALID_USER;

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', trans($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => trans($status)]);
    }
}
