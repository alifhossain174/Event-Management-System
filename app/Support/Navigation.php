<?php

namespace App\Support;

use App\Models\User;

final class Navigation
{
    public function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [
            [
                'label' => 'Workspace',
                'items' => [
                    [
                        'label' => 'Overview',
                        'route' => 'dashboard',
                        'patterns' => ['dashboard'],
                        'icon' => 'home',
                        'permission' => 'dashboard.view',
                    ],
                    [
                        'label' => 'Search',
                        'route' => 'search',
                        'patterns' => ['search'],
                        'icon' => 'search',
                        'permission' => 'dashboard.view',
                    ],
                    [
                        'label' => 'Clients',
                        'route' => 'clients.index',
                        'patterns' => ['clients.*'],
                        'icon' => 'users',
                        'permission' => 'clients.view',
                    ],
                    [
                        'label' => 'Events',
                        'route' => 'events.index',
                        'patterns' => ['events.*'],
                        'icon' => 'calendar',
                        'permission' => 'events.view',
                    ],
                    [
                        'label' => 'Calendar',
                        'route' => 'calendar.index',
                        'patterns' => ['calendar.*'],
                        'icon' => 'calendar',
                        'permission' => 'calendar.view',
                    ],
                    [
                        'label' => 'Bookings',
                        'route' => 'bookings.index',
                        'patterns' => ['bookings.*'],
                        'icon' => 'calendar',
                        'permission' => 'bookings.view',
                    ],
                    [
                        'label' => 'Payment dues',
                        'route' => 'payments.due',
                        'patterns' => ['payments.due'],
                        'icon' => 'list',
                        'permission' => 'payments.view',
                    ],
                    [
                        'label' => 'Notifications',
                        'route' => 'notifications.index',
                        'patterns' => ['notifications.*'],
                        'icon' => 'bell',
                        'permission' => 'notifications.view',
                    ],
                    [
                        'label' => 'Venues',
                        'route' => 'venues.index',
                        'patterns' => ['venues.*'],
                        'icon' => 'home',
                        'permission' => 'venues.view',
                    ],
                    [
                        'label' => 'Vendors', 'route' => 'vendors.index', 'patterns' => ['vendors.*'],
                        'icon' => 'users', 'permission' => 'vendors.view', 'alternate_permission' => 'vendors.view-own',
                    ],
                    [
                        'label' => 'Staff', 'route' => 'staff.index', 'patterns' => ['staff.*'],
                        'icon' => 'users', 'permission' => 'staff.view', 'alternate_permission' => 'staff.view-own',
                    ],
                    [
                        'label' => 'Staff operations', 'route' => 'staff.operations.index', 'patterns' => ['staff.operations.*'],
                        'icon' => 'calendar', 'permission' => 'staff.view-work', 'alternate_permission' => 'staff.view-own-work',
                    ],
                ],
            ],
            [
                'label' => 'Administration',
                'items' => [
                    [
                        'label' => 'Users',
                        'route' => 'users.index',
                        'patterns' => ['users.*'],
                        'icon' => 'users',
                        'permission' => 'users.view',
                    ],
                    [
                        'label' => 'Documents',
                        'route' => 'documents.index',
                        'patterns' => ['documents.*'],
                        'icon' => 'list',
                        'permission' => 'documents.view',
                    ],
                    [
                        'label' => 'Audit history',
                        'route' => 'audit.index',
                        'patterns' => ['audit.*'],
                        'icon' => 'lock',
                        'permission' => 'audit.view',
                    ],
                    [
                        'label' => 'Organization settings',
                        'route' => 'settings.edit',
                        'patterns' => ['settings.edit'],
                        'icon' => 'settings',
                        'permission' => 'settings.view',
                    ],
                    [
                        'label' => 'Branches',
                        'route' => 'settings.branches.index',
                        'patterns' => ['settings.branches.*'],
                        'icon' => 'branch',
                        'permission' => 'branches.view',
                    ],
                    [
                        'label' => 'Master data',
                        'route' => 'settings.master-data.index',
                        'parameters' => ['type' => 'event-categories'],
                        'patterns' => ['settings.master-data.*'],
                        'icon' => 'list',
                        'permission' => 'master-data.view',
                    ],
                    [
                        'label' => 'Event templates',
                        'route' => 'settings.event-templates.index',
                        'patterns' => ['settings.event-templates.*', 'settings.module-definitions.*'],
                        'icon' => 'list',
                        'permission' => 'event-templates.view',
                    ],
                    [
                        'label' => 'Message templates',
                        'route' => 'message-templates.index',
                        'patterns' => ['message-templates.*'],
                        'icon' => 'list',
                        'permission' => 'communications.configure',
                    ],
                    ...app()->environment(['local', 'testing']) ? [[
                        'label' => 'UI style guide',
                        'route' => 'style-guide',
                        'patterns' => ['style-guide'],
                        'icon' => 'palette',
                        'permission' => null,
                    ]] : [],
                ],
            ],
        ];

        return collect($groups)
            ->map(function (array $group) use ($user) {
                $group['items'] = collect($group['items'])
                    ->filter(fn ($item) => $item['permission'] === null || $user->hasPermission($item['permission']) || (isset($item['alternate_permission']) && $user->hasPermission($item['alternate_permission'])))
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn ($group) => $group['items'] !== [])
            ->values()
            ->all();
    }
}
