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
                        'route' => 'home',
                        'patterns' => ['home'],
                        'icon' => 'home',
                        'permission' => null,
                    ],
                    [
                        'label' => 'Clients',
                        'route' => 'clients.index',
                        'patterns' => ['clients.*'],
                        'icon' => 'users',
                        'permission' => 'clients.view',
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
                    ->filter(fn ($item) => $item['permission'] === null || $user->hasPermission($item['permission']))
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn ($group) => $group['items'] !== [])
            ->values()
            ->all();
    }
}
