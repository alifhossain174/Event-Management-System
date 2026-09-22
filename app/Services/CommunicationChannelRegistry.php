<?php

namespace App\Services;

use App\Contracts\OutboundMessageChannel;
use InvalidArgumentException;

final class CommunicationChannelRegistry
{
    /** @var array<string, OutboundMessageChannel> */
    private array $channels = [];

    /** @param iterable<OutboundMessageChannel> $channels */
    public function __construct(iterable $channels)
    {
        foreach ($channels as $channel) {
            $this->channels[$channel->key()] = $channel;
        }
    }

    public function get(string $key): OutboundMessageChannel
    {
        return $this->channels[$key] ?? throw new InvalidArgumentException("Unknown communication channel [{$key}].");
    }

    public function availability(): array
    {
        return collect($this->channels)->map->isAvailable()->all();
    }
}
