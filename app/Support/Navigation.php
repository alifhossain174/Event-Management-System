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

        return collect([
            ['label' => 'Home', 'route' => 'home', 'permission' => null],
            ['label' => 'Users', 'route' => 'users.index', 'permission' => 'users.view'],
            ['label' => 'Profile', 'route' => 'profile.edit', 'permission' => null],
        ])->filter(fn ($item) => $item['permission'] === null || $user->hasPermission($item['permission']))
            ->values()
            ->all();
    }
}
