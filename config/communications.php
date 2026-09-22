<?php

use App\Services\DisabledSmsChannel;
use App\Services\DisabledWhatsAppChannel;
use App\Services\EmailOutboundChannel;

return [
    'channels' => [
        EmailOutboundChannel::class,
        DisabledSmsChannel::class,
        DisabledWhatsAppChannel::class,
    ],

    // Store route names and parameters, never arbitrary URLs. Every destination is still authorized server-side.
    'safe_notification_routes' => [
        'notifications.index',
        'events.show',
        'bookings.show',
        'events.payments.index',
        'events.vendors.show',
        'events.staff.index',
        'documents.show',
        'events.communications.index',
    ],
];
