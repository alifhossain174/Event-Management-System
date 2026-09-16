<?php

return [
    'definitions' => [
        'general.timezone' => [
            'type' => 'string',
            'default' => 'UTC',
        ],
        'general.currency' => [
            'type' => 'string',
            'default' => 'USD',
        ],
        'general.locale' => [
            'type' => 'string',
            'default' => 'en',
        ],
        'general.date_format' => [
            'type' => 'string',
            'default' => 'Y-m-d',
        ],
        'finance.default_tax_rate' => [
            'type' => 'decimal',
            'default' => '0.000000',
        ],
        'invoice.prefix' => [
            'type' => 'string',
            'default' => 'INV-',
        ],
        'invoice.next_number' => [
            'type' => 'integer',
            'default' => 1,
        ],
        'invoice.number_padding' => [
            'type' => 'integer',
            'default' => 6,
        ],
        'features.branches_enabled' => [
            'type' => 'boolean',
            'default' => false,
        ],
        'features.client_portal_enabled' => [
            'type' => 'boolean',
            'default' => false,
        ],
        'features.vendor_portal_enabled' => [
            'type' => 'boolean',
            'default' => false,
        ],
        'features.staff_portal_enabled' => [
            'type' => 'boolean',
            'default' => false,
        ],
        'features.communications_enabled' => [
            'type' => 'boolean',
            'default' => false,
        ],
        'integrations.email_secret_placeholder' => [
            'type' => 'secret',
            'default' => null,
        ],
        'integrations.sms_secret_placeholder' => [
            'type' => 'secret',
            'default' => null,
        ],
        'integrations.whatsapp_secret_placeholder' => [
            'type' => 'secret',
            'default' => null,
        ],
    ],

    'timezones' => DateTimeZone::listIdentifiers(),

    'currencies' => ['BDT', 'EUR', 'GBP', 'INR', 'USD'],
    'locales' => [
        'en' => 'English',
        'bn' => 'Bangla',
        'de' => 'German',
    ],
    'date_formats' => ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'],
];
