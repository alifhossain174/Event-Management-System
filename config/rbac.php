<?php

return [
    'permissions' => [
        'dashboard.view' => 'View the dashboard',
        'users.view' => 'View users',
        'users.create' => 'Create users',
        'users.update' => 'Update users',
        'users.delete' => 'Archive users',
        'users.assign' => 'Assign user roles',
        'events.view' => 'View events',
        'events.create' => 'Create events',
        'events.update' => 'Update events',
        'events.delete' => 'Archive events',
        'events.approve' => 'Approve events and workflow transitions',
        'events.export' => 'Export event data',
        'events.assign' => 'Assign event work',
        'payments.view' => 'View payments',
        'payments.create' => 'Record payments',
        'payments.refund' => 'Record refunds',
        'payments.export' => 'Export payment data',
        'settings.view' => 'View settings',
        'settings.configure' => 'Configure settings',
        'branches.view' => 'View branches',
        'branches.configure' => 'Configure branches',
        'master-data.view' => 'View configurable master data',
        'master-data.configure' => 'Configure master data',
        'audit.view' => 'View audit history',
        'clients.view' => 'View clients',
        'clients.create' => 'Create clients',
        'clients.update' => 'Update clients and contacts',
        'clients.delete' => 'Archive and reactivate clients',
        'clients.assign' => 'Link client portal users',
        'clients.merge' => 'Merge duplicate clients',
        'documents.view' => 'View authorized documents',
        'documents.create' => 'Upload documents',
        'documents.update' => 'Replace document versions',
        'documents.delete' => 'Archive documents',
        'documents.download' => 'Download authorized documents',
    ],

    'roles' => [
        'administrator' => [
            'name' => 'Administrator / Business Manager',
            'permissions' => ['*'],
        ],
        'event-manager' => [
            'name' => 'Event Manager',
            'permissions' => [
                'dashboard.view', 'events.view', 'events.create', 'events.update',
                'events.delete', 'events.approve', 'events.export', 'events.assign',
                'clients.view', 'clients.create', 'clients.update',
            ],
        ],
        'staff' => [
            'name' => 'Staff',
            'permissions' => ['dashboard.view', 'events.view'],
        ],
        'client' => [
            'name' => 'Client',
            'permissions' => ['dashboard.view'],
        ],
        'vendor' => [
            'name' => 'Vendor',
            'permissions' => ['dashboard.view'],
        ],
        'finance-accounts' => [
            'name' => 'Finance / Accounts',
            'permissions' => [
                'dashboard.view', 'events.view', 'payments.view', 'payments.create',
                'payments.refund', 'payments.export',
            ],
        ],
        'front-desk-check-in' => [
            'name' => 'Front Desk / Check-in',
            'permissions' => ['dashboard.view', 'events.view'],
        ],
    ],

    'development_administrator' => [
        'name' => env('DEV_ADMIN_NAME'),
        'email' => env('DEV_ADMIN_EMAIL'),
        'password' => env('DEV_ADMIN_PASSWORD'),
    ],
];
