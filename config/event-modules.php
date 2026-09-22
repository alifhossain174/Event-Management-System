<?php

use App\Services\BudgetDataDetector;
use App\Services\CommunicationDataDetector;
use App\Services\EventDocumentDataDetector;
use App\Services\GuestDataDetector;
use App\Services\InvoiceDataDetector;
use App\Services\PaymentDataDetector;
use App\Services\RegistrationDataDetector;
use App\Services\StaffAssignmentDataDetector;
use App\Services\TaskDataDetector;
use App\Services\TicketDataDetector;
use App\Services\VendorAssignmentDataDetector;
use App\Services\VenueAllocationDataDetector;

return [
    'definitions' => [
        'dashboard' => ['label' => 'Dashboard', 'scope' => 'global', 'event_toggle' => false, 'order' => 10],
        'users' => ['label' => 'User Management', 'scope' => 'global', 'event_toggle' => false, 'order' => 20],
        'events' => ['label' => 'Event Management', 'scope' => 'core', 'event_toggle' => false, 'order' => 30],
        'clients' => ['label' => 'Client Management', 'scope' => 'global_master', 'event_toggle' => false, 'order' => 40],
        'booking' => ['label' => 'Booking Management', 'scope' => 'optional_upstream', 'event_toggle' => false, 'order' => 50],
        'venue' => ['label' => 'Venue', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 60],
        'vendors' => ['label' => 'Vendors', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 70],
        'staff' => ['label' => 'Staff', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 80],
        'guests' => ['label' => 'Guests', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 90],
        'ticketing' => ['label' => 'Ticketing', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 100],
        'registration' => ['label' => 'Registration', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 110],
        'tasks' => ['label' => 'Tasks', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 120],
        'budget' => ['label' => 'Budget', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 130],
        'payments' => ['label' => 'Payments', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 140],
        'invoices' => ['label' => 'Invoices', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 150],
        'inventory' => ['label' => 'Inventory', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 160],
        'catering' => ['label' => 'Catering', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 170],
        'decoration' => ['label' => 'Decoration', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 180],
        'transportation' => ['label' => 'Transportation', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 190],
        'accommodation' => ['label' => 'Accommodation', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 200],
        'marketing' => ['label' => 'Marketing', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 210],
        'communications' => ['label' => 'Communications', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 220],
        'calendar' => ['label' => 'Calendar', 'scope' => 'global_projection', 'event_toggle' => false, 'order' => 230],
        'documents' => ['label' => 'Documents', 'scope' => 'event_scoped', 'event_toggle' => true, 'order' => 240],
        'reports' => ['label' => 'Reports', 'scope' => 'global_projection', 'event_toggle' => false, 'order' => 250],
        'analytics' => ['label' => 'Analytics', 'scope' => 'global_projection', 'event_toggle' => false, 'order' => 260],
        'notifications' => ['label' => 'Notifications', 'scope' => 'global', 'event_toggle' => false, 'order' => 270],
        'settings' => ['label' => 'Settings', 'scope' => 'global', 'event_toggle' => false, 'order' => 280],
        'audit' => ['label' => 'Audit and Security', 'scope' => 'global', 'event_toggle' => false, 'order' => 290],
    ],

    'event_scoped_keys' => [
        'venue', 'vendors', 'staff', 'tasks', 'guests', 'registration', 'ticketing',
        'budget', 'payments', 'invoices', 'inventory', 'catering', 'decoration',
        'transportation', 'accommodation', 'marketing', 'documents', 'communications',
    ],

    'view_permissions' => [
        'venue' => 'venues.view',
        'vendors' => 'vendors.view-work',
        'staff' => 'staff.view-work',
        'tasks' => 'tasks.view',
        'guests' => 'guests.view',
        'registration' => 'registrations.view',
        'ticketing' => 'tickets.view',
        'budget' => 'budget.view',
        'payments' => 'payments.view',
        'invoices' => 'invoices.view',
        'inventory' => 'events.view',
        'catering' => 'events.view',
        'decoration' => 'events.view',
        'transportation' => 'events.view',
        'accommodation' => 'events.view',
        'marketing' => 'events.view',
        'documents' => 'documents.view',
        'communications' => 'communications.view',
    ],

    'data_detectors' => [
        'venue' => VenueAllocationDataDetector::class,
        'vendors' => VendorAssignmentDataDetector::class,
        'staff' => StaffAssignmentDataDetector::class,
        'tasks' => TaskDataDetector::class,
        'guests' => GuestDataDetector::class,
        'registration' => RegistrationDataDetector::class,
        'ticketing' => TicketDataDetector::class,
        'budget' => BudgetDataDetector::class,
        'payments' => PaymentDataDetector::class,
        'invoices' => InvoiceDataDetector::class,
        'documents' => EventDocumentDataDetector::class,
        'communications' => CommunicationDataDetector::class,
    ],

    'dependencies' => [
        'guests' => [['keys' => ['venue'], 'message' => 'Guest seating and check-in are easier to plan with Venue enabled.']],
        'registration' => [['keys' => ['guests'], 'message' => 'Registration can create or update Guest records when Guest Management is enabled.']],
        'ticketing' => [['keys' => ['registration', 'guests'], 'message' => 'Ticketing can integrate attendee and check-in data with Registration or Guests.']],
        'catering' => [['keys' => ['guests'], 'message' => 'Catering may use Guest counts, but an independent quantity is allowed.']],
        'decoration' => [['keys' => ['vendors', 'inventory'], 'message' => 'Decoration can link Vendor or Inventory resources, but notes-only planning is allowed.']],
        'transportation' => [['keys' => ['guests'], 'message' => 'Transportation can link Guest or group pickups, but independent routes are allowed.']],
        'accommodation' => [['keys' => ['guests'], 'message' => 'Accommodation can allocate named Guests, but block bookings are allowed first.']],
        'budget' => [['keys' => ['payments', 'invoices'], 'message' => 'Payments and Invoices improve Budget reconciliation but are not required.']],
    ],
];
