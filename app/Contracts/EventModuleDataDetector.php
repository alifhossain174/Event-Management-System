<?php

namespace App\Contracts;

use App\Models\Event;

interface EventModuleDataDetector
{
    public function moduleKey(): string;

    public function hasData(Event $event): bool;
}
