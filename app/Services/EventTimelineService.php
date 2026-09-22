<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTimelineItem;
use App\Models\User;

final class EventTimelineService
{
    /** @param array<string, mixed> $metadata */
    public function record(Event $event, string $type, string $title, ?User $actor, ?string $description = null, array $metadata = []): EventTimelineItem
    {
        return $event->timelineItems()->create([
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'actor_user_id' => $actor?->getKey(),
            'occurred_at' => now(),
        ]);
    }
}
