<?php

namespace App\Services;

use App\Contracts\OutboundMessageChannel;
use App\Exceptions\CommunicationChannelUnavailable;
use App\Models\MessageRecipient;
use App\Models\OutboundMessage;

final class DisabledWhatsAppChannel implements OutboundMessageChannel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    public function providerName(): string
    {
        return 'disabled-whatsapp-adapter';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function send(OutboundMessage $message, MessageRecipient $recipient): ?string
    {
        throw new CommunicationChannelUnavailable('WhatsApp delivery is disabled until a provider is configured.');
    }
}
