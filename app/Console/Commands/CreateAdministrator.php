<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CreateAdministrator extends Command
{
    protected $signature = 'app:create-administrator {--name=} {--email=}';

    protected $description = 'Create an active Administrator / Business Manager using interactive credentials';

    public function handle(): int
    {
        app(RolePermissionSeeder::class)->run();

        $name = $this->option('name') ?: $this->ask('Name');
        $email = mb_strtolower($this->option('email') ?: $this->ask('Email'));
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);
        $validator->after(function ($validator) use ($password, $confirmation) {
            if ($password !== $confirmation) {
                $validator->errors()->add('password', 'The password confirmation does not match.');
            }
        });

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        $this->info("Administrator created for {$email}.");

        return self::SUCCESS;
    }
}
