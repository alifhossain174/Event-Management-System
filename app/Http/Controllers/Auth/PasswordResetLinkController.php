<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

final class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = mb_strtolower($request->validated('email'));
        $activeUserExists = User::query()->where('email', $email)->where('is_active', true)->exists();

        if ($activeUserExists) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', trans(Password::RESET_LINK_SENT));
    }
}
