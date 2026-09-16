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
