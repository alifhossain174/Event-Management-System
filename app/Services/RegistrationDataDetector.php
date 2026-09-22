<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class RegistrationDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'registration';
    }

    public function hasData(Event $event): bool
    {
        return $event->registrationForms()->exists() || $event->registrations()->exists();
    }
}
