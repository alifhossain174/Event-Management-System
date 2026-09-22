<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrationWorkflowService
{
    public function __construct(private readonly CommunicationService $communications, private readonly AuditService $audit) {}

    public function transition(Registration $registration, string $status, array $data, ?User $actor): Registration
    {
        $registration = DB::transaction(function () use ($registration, $status, $data, $actor) {
            $registration = Registration::query()->lockForUpdate()->with(['form', 'event'])->findOrFail($registration->getKey());
            if (! in_array($status, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Select Approved or Rejected.']);
            }
            if ($registration->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending registrations can be reviewed.']);
            }
            if ($status === 'rejected' && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
            }

            $from = $registration->status;
            $registration->update([
                'status' => $status,
                'reviewed_by_user_id' => $actor?->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                'rejection_reason' => $status === 'rejected' ? $data['reason'] : null,
                'confirmation_status' => $status === 'approved' && $registration->form->confirmation_channel !== 'none' ? 'pending' : 'not_requested',
            ]);
            RegistrationStatusHistory::query()->create([
                'registration_id' => $registration->getKey(), 'from_status' => $from, 'to_status' => $status,
                'actor_type' => $actor ? 'user' : 'system', 'actor_user_id' => $actor?->getKey(),
                'reason' => $data['reason'] ?? $data['review_notes'] ?? null, 'changed_at' => now(),
                'metadata' => ['confirmation_channel' => $registration->form->confirmation_channel],
            ]);
            $this->audit->record('registration.'.$status, $registration, ['status' => $from], [
                'status' => $status, 'event_id' => $registration->event_id,
            ], $actor);

            return $registration;
        }, 3);

        if ($status === 'approved') {
            $this->sendConfirmation($registration, $actor);
        }

        return $registration->fresh(['responses', 'statusHistory', 'outboundMessages']);
    }

    private function sendConfirmation(Registration $registration, ?User $actor): void
    {
        $channel = $registration->form->confirmation_channel;
        if ($channel === 'none') {
            return;
        }
        $address = $channel === 'email' ? $registration->registrant_email : $registration->registrant_phone;
        $communicationsEnabled = $registration->event->moduleSettings()
            ->where('module_key', 'communications')->where('is_enabled', true)->exists();
        if (! $communicationsEnabled || blank($address)) {
            $registration->update(['confirmation_status' => 'skipped']);

            return;
        }

        $message = $this->communications->compose($registration->event, [
            'registration_id' => $registration->getKey(),
            'channel' => $channel,
            'category' => 'operational',
            'subject' => 'Registration approved: '.$registration->event->name,
            'body' => "Your registration {$registration->reference_number} for {$registration->event->name} has been approved.",
            'recipient_address' => $address,
            'recipient_name' => $registration->registrant_name,
            'idempotency_key' => 'registration-confirmation-'.$registration->getKey(),
        ], $actor);
        $registration->update([
            'confirmation_status' => $message->status,
            'confirmation_sent_at' => $message->status === 'sent' ? now() : null,
        ]);
    }
}
