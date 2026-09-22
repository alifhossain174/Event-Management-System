<?php

namespace App\Services;

use App\Contracts\RegistrationSpamGuard;
use Illuminate\Validation\ValidationException;

final class HoneypotRegistrationSpamGuard implements RegistrationSpamGuard
{
    public function validate(array $input): void
    {
        if (filled($input['website'] ?? null)) {
            throw ValidationException::withMessages(['registration' => 'The registration could not be accepted.']);
        }
    }
}
