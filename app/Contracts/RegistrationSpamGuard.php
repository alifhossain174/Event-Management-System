<?php

namespace App\Contracts;

interface RegistrationSpamGuard
{
    /** @param array<string, mixed> $input */
    public function validate(array $input): void;
}
