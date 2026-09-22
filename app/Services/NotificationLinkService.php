<?php

namespace App\Services;

use App\Models\InAppNotification;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

final class NotificationLinkService
{
    public function assertSafe(?string $routeName): void
    {
        if ($routeName === null) {
            return;
        }

        if (! in_array($routeName, config('communications.safe_notification_routes', []), true) || ! Route::has($routeName)) {
            throw new InvalidArgumentException("Route [{$routeName}] is not allowed for notification links.");
        }
    }

    public function url(InAppNotification $notification): ?string
    {
        if (! $notification->route_name) {
            return null;
        }

        $this->assertSafe($notification->route_name);

        return route($notification->route_name, $notification->route_parameters ?? []);
    }
}
