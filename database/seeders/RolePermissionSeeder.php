<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(config('rbac.permissions'))->mapWithKeys(function ($description, $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => Str::headline(str_replace('.', ' ', $slug)), 'description' => $description],
            );

            return [$slug => $permission->id];
        });

        foreach (config('rbac.roles') as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'is_system' => true],
            );

            $rolePermissions = $definition['permissions'] === ['*']
                ? $permissions->values()->all()
                : $permissions->only($definition['permissions'])->values()->all();

            $role->permissions()->sync($rolePermissions);
        }
    }
}
