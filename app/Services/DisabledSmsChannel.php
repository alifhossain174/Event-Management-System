<?php

namespace App\Services;

use App\Contracts\OutboundMessageChannel;
use App\Exceptions\CommunicationChannelUnavailable;
use App\Models\MessageRecipient;
use App\Models\OutboundMessage;

final class DisabledSmsChannel implements OutboundMessageChannel
{
    public function key(): string
    {
        return 'sms';
    }

    public function providerName(): string
    {
        return 'disabled-sms-adapter';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function send(OutboundMessage $message, MessageRecipient $recipient): ?string
    {
        throw new CommunicationChannelUnavailable('SMS delivery is disabled until a provider is configured.');
    }
}
