<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrationGuestService
{
    public function __construct(private readonly GuestService $guests, private readonly AuditService $audit) {}

    public function convert(Registration $registration, User $actor, ?int $existingGuestId = null): Guest
    {
        return DB::transaction(function () use ($registration, $actor, $existingGuestId) {
            $registration = Registration::query()->lockForUpdate()->with('event')->findOrFail($registration->getKey());
            if ($registration->status !== 'approved') {
                throw ValidationException::withMessages(['guest' => 'Approve the registration before creating a Guest.']);
            }
            if ($registration->guest_id) {
                return Guest::query()->findOrFail($registration->guest_id);
            }
            if (! $registration->event->moduleSettings()->where('module_key', 'guests')->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages(['guest' => 'Enable Guest Management before converting this registration.']);
            }

            if ($existingGuestId) {
                $guest = Guest::query()->whereKey($existingGuestId)->where('event_id', $registration->event_id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
                $guest->update([
                    'email' => $guest->email ?: $registration->registrant_email,
                    'normalized_email' => $guest->normalized_email ?: $registration->normalized_email,
                    'phone' => $guest->phone ?: $registration->registrant_phone,
                    'normalized_phone' => $guest->normalized_phone ?: preg_replace('/\D+/', '', (string) $registration->registrant_phone),
                    'updated_by_user_id' => $actor->getKey(),
                ]);
            } else {
                [$first, $last] = array_pad(preg_split('/\s+/', trim($registration->registrant_name), 2) ?: [], 2, null);
                $guest = $this->guests->create($registration->event, [
                    'external_reference' => $registration->reference_number,
                    'first_name' => $first ?: $registration->registrant_name,
                    'last_name' => $last,
                    'email' => $registration->registrant_email,
                    'phone' => $registration->registrant_phone,
                    'is_vip' => false,
                    'plus_one_policy' => 'none',
                    'plus_one_limit' => 0,
                    'invited_party_size' => 1,
                    'source' => 'registration',
                ], $actor);
            }

            $registration->update(['guest_id' => $guest->getKey()]);
            $this->audit->record('registration.guest_linked', $registration, [], ['guest_id' => $guest->getKey()], $actor);

            return $guest;
        }, 3);
    }
}
