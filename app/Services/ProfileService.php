<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProfileService
{
    public function __construct(private readonly AuditService $audit) {}

    public function update(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $before = $user->only(['name', 'email']);
            $user->update([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
            ]);

            $this->audit->record(
                'user.profile_updated',
                $user,
                $before,
                $user->only(['name', 'email']),
                $user,
            );
        });
    }

    public function updatePassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password])->save();
            $this->audit->record('user.password_changed', $user, actor: $user);
        });
    }
}
