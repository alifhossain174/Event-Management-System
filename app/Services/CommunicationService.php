<?php

namespace App\Services;

use App\Exceptions\CommunicationChannelUnavailable;
use App\Models\DeliveryLog;
use App\Models\Event;
use App\Models\MessageRecipient;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CommunicationService
{
    public function __construct(
        private readonly CommunicationChannelRegistry $channels,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function compose(Event $event, array $data, ?User $actor): OutboundMessage
    {
        $template = isset($data['message_template_id'])
            ? MessageTemplate::query()->active()->findOrFail($data['message_template_id'])
            : null;
        if ($template) {
            $data['channel'] = $template->channel;
            $data['category'] = $template->category;
            $data['subject'] = filled($data['subject'] ?? null) ? $data['subject'] : $template->subject;
            $data['body'] = filled($data['body'] ?? null) ? $data['body'] : $template->body;
        }

        if (($data['category'] ?? 'operational') === 'marketing' && ! ($data['marketing_consent_confirmed'] ?? false)) {
            throw ValidationException::withMessages(['marketing_consent_confirmed' => 'Explicit marketing consent must be confirmed for marketing messages.']);
        }

        $message = DB::transaction(function () use ($event, $data, $actor) {
            if ($key = $data['idempotency_key'] ?? null) {
                if ($existing = OutboundMessage::query()->where('idempotency_key', $key)->first()) {
                    return $existing;
                }
            }

            $message = OutboundMessage::query()->create([
                'message_template_id' => $data['message_template_id'] ?? null,
                'event_id' => $event->getKey(),
                'client_id' => $event->client_id,
                'booking_id' => $event->booking_id,
                'registration_id' => $data['registration_id'] ?? null,
                'ticket_order_id' => $data['ticket_order_id'] ?? null,
                'channel' => $data['channel'],
                'category' => $data['category'] ?? 'operational',
                'status' => 'pending',
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'],
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by_user_id' => $actor?->getKey(),
            ]);
            $message->recipients()->create([
                'recipient_type' => 'to',
                'address' => $data['recipient_address'],
                'display_name' => $data['recipient_name'] ?? null,
                'consent_basis' => $message->category === 'marketing' ? 'marketing_opt_in_confirmed' : 'operational',
                'status' => 'pending',
            ]);
            $this->audit->record('communication.created', $message, [], [
                'event_id' => $event->getKey(), 'channel' => $message->channel,
                'category' => $message->category, 'recipient_count' => 1,
            ], $actor);

            return $message;
        });

        if ($message->status === 'pending') {
            $this->dispatch($message, $actor);
        }

        return $message->fresh(['recipients', 'deliveryLogs']);
    }

    public function retry(OutboundMessage $message, User $actor): OutboundMessage
    {
        if ($message->status !== 'failed') {
            throw ValidationException::withMessages(['message' => 'Only failed messages can be retried.']);
        }

        return $this->dispatch($message, $actor);
    }

    public function dispatch(OutboundMessage $message, ?User $actor = null): OutboundMessage
    {
        $channel = $this->channels->get($message->channel);
        $message->load('recipients');

        foreach ($message->recipients->whereIn('status', ['pending', 'failed']) as $recipient) {
            $attempt = ((int) $recipient->deliveryLogs()->max('attempt_number')) + 1;
            $attemptedAt = now();
            try {
                $reference = $channel->send($message, $recipient);
                $recipient->update(['status' => 'sent', 'sent_at' => $attemptedAt, 'failed_at' => null, 'last_error_code' => null]);
                $this->log($message, $recipient, $attempt, $channel->providerName(), 'sent', $reference, null, $actor, $attemptedAt);
            } catch (Throwable $exception) {
                $code = $exception instanceof CommunicationChannelUnavailable ? 'provider_unavailable' : 'delivery_failed';
                $recipient->update(['status' => 'failed', 'failed_at' => $attemptedAt, 'last_error_code' => $code]);
                $this->log($message, $recipient, $attempt, $channel->providerName(), 'failed', null, $code, $actor, $attemptedAt);
            }
        }

        $statuses = $message->recipients()->pluck('status');
        $sent = $statuses->isNotEmpty() && $statuses->every(fn (string $status) => $status === 'sent');
        $now = now();
        $message->update([
            'status' => $sent ? 'sent' : 'failed',
            'attempt_count' => $message->attempt_count + 1,
            'last_attempt_at' => $now,
            'sent_at' => $sent ? $now : null,
            'failed_at' => $sent ? null : $now,
        ]);
        $this->audit->record($sent ? 'communication.sent' : 'communication.failed', $message, [], [
            'channel' => $message->channel, 'status' => $message->status,
            'error_code' => $sent ? null : 'delivery_failed_or_unavailable',
        ], $actor);

        return $message->fresh(['recipients', 'deliveryLogs']);
    }

    private function log(OutboundMessage $message, MessageRecipient $recipient, int $attempt, string $provider, string $status, ?string $reference, ?string $error, ?User $actor, $attemptedAt): void
    {
        DeliveryLog::query()->create([
            'outbound_message_id' => $message->getKey(), 'message_recipient_id' => $recipient->getKey(),
            'attempt_number' => $attempt, 'provider' => Str::limit($provider, 64, ''), 'status' => $status,
            'provider_reference' => $reference ? Str::limit($reference, 191, '') : null,
            'sanitized_error' => $error, 'attempted_by_user_id' => $actor?->getKey(), 'attempted_at' => $attemptedAt,
        ]);
    }
}
