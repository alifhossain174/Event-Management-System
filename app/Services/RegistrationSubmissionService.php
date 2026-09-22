<?php

namespace App\Services;

use App\Contracts\RegistrationSpamGuard;
use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Models\RegistrationResponse;
use App\Models\RegistrationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegistrationSubmissionService
{
    public function __construct(
        private readonly RegistrationSpamGuard $spamGuard,
        private readonly AuditService $audit,
        private readonly RegistrationWorkflowService $workflow,
    ) {}

    /** @param array<string, mixed> $data */
    public function submit(RegistrationForm $form, array $data, string $source, ?User $actor = null): Registration
    {
        $registration = DB::transaction(function () use ($form, $data, $source, $actor) {
            $this->spamGuard->validate($data);
            if ($existing = Registration::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                if ($existing->registration_form_id !== $form->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission token has already been used.']);
                }

                return $existing;
            }

            $email = filled($data['registrant_email'] ?? null) ? mb_strtolower(trim($data['registrant_email'])) : null;
            if ($email && $form->duplicate_policy === 'block_email' && $form->registrations()->where('normalized_email', $email)->whereNot('status', 'rejected')->exists()) {
                throw ValidationException::withMessages(['registrant_email' => 'A registration already exists for this email address.']);
            }

            $registration = Registration::query()->create([
                'event_id' => $form->event_id,
                'registration_form_id' => $form->getKey(),
                'reference_number' => 'REG-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $data['idempotency_key'],
                'source' => $source,
                'status' => 'pending',
                'registrant_name' => trim($data['registrant_name']),
                'registrant_email' => $email,
                'normalized_email' => $email,
                'registrant_phone' => filled($data['registrant_phone'] ?? null) ? trim($data['registrant_phone']) : null,
                'submitted_at' => now(),
                'submitted_by_user_id' => $actor?->getKey(),
                'confirmation_status' => 'not_requested',
            ]);

            $responses = $data['responses'] ?? [];
            foreach ($form->fields as $field) {
                $value = $responses[$field->key] ?? null;
                RegistrationResponse::query()->create([
                    'event_id' => $form->event_id,
                    'registration_id' => $registration->getKey(),
                    'registration_field_id' => $field->getKey(),
                    'field_key' => $field->key,
                    'field_label' => $field->label,
                    'field_type' => $field->type,
                    'value_text' => is_array($value) ? null : (is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value)),
                    'value_json' => is_array($value) ? array_values($value) : null,
                ]);
            }
            RegistrationStatusHistory::query()->create([
                'registration_id' => $registration->getKey(), 'from_status' => null, 'to_status' => 'pending',
                'actor_type' => $actor ? 'user' : 'public', 'actor_user_id' => $actor?->getKey(),
                'reason' => 'Registration submitted.', 'changed_at' => now(),
                'metadata' => ['source' => $source],
            ]);
            $this->audit->record('registration.submitted', $registration, [], [
                'event_id' => $form->event_id, 'form_id' => $form->getKey(), 'source' => $source,
                'reference_number' => $registration->reference_number,
            ], $actor);

            return $registration->fresh(['responses', 'statusHistory']);
        }, 3);

        if (! $form->approval_required && $registration->status === 'pending') {
            return $this->workflow->transition($registration, 'approved', ['review_notes' => 'Automatically approved by form configuration.'], $actor);
        }

        return $registration;
    }
}
