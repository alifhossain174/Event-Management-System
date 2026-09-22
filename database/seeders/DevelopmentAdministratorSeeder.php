<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DevelopmentAdministratorSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $credentials = config('rbac.development_administrator');

        if (! $credentials['email'] || ! $credentials['password']) {
            $this->command?->warn('Development administrator skipped: set DEV_ADMIN_EMAIL and DEV_ADMIN_PASSWORD.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => mb_strtolower($credentials['email'])],
            [
                'name' => $credentials['name'] ?: 'Development Administrator',
                'password' => $credentials['password'],
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->syncWithoutDetaching([
            $role->id => ['assigned_at' => now(), 'assigned_by_user_id' => null],
        ]);
    }
}
