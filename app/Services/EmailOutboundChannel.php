<?php

namespace App\Services;

use App\Contracts\OutboundMessageChannel;
use App\Exceptions\CommunicationChannelUnavailable;
use App\Models\MessageRecipient;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Mail;

final class EmailOutboundChannel implements OutboundMessageChannel
{
    public function __construct(private readonly SettingsService $settings) {}

    public function key(): string
    {
        return 'email';
    }

    public function providerName(): string
    {
        return 'laravel-mail';
    }

    public function isAvailable(): bool
    {
        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        if (app()->environment(['local', 'testing']) && in_array($transport, ['array', 'log'], true)) {
            return true;
        }

        if (! $this->settings->boolean('features.communications_enabled')) {
            return false;
        }

        if ($transport === 'smtp') {
            return filled(config("mail.mailers.{$mailer}.host"))
                && filled(config('mail.from.address'))
                && config('mail.from.address') !== 'hello@example.com';
        }

        return in_array($transport, ['sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true)
            && filled(config('mail.from.address'));
    }

    public function send(OutboundMessage $message, MessageRecipient $recipient): ?string
    {
        if (! $this->isAvailable()) {
            throw new CommunicationChannelUnavailable('Email delivery is not configured.');
        }

        Mail::raw($message->body, function ($mail) use ($message, $recipient) {
            $mail->to($recipient->address, $recipient->display_name)->subject($message->subject ?: 'Event Management message');
        });

        return null;
    }
}
