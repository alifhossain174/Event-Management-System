<?php

namespace App\Contracts;

use App\Models\MessageRecipient;
use App\Models\OutboundMessage;

interface OutboundMessageChannel
{
    public function key(): string;

    public function providerName(): string;

    public function isAvailable(): bool;

    public function send(OutboundMessage $message, MessageRecipient $recipient): ?string;
}
