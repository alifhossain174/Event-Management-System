<?php

namespace App\Services;

use App\Models\GuestCheckIn;
use App\Models\Invitation;

final readonly class GuestCheckInResult
{
    public function __construct(
        public Invitation $invitation,
        public ?GuestCheckIn $checkIn,
        public bool $alreadyCheckedIn,
    ) {}
}
