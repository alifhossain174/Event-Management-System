<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserManagementService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
                'deactivated_at' => ($data['is_active'] ?? true) ? null : now(),
                'deactivated_by_user_id' => ($data['is_active'] ?? true) ? null : $actor->id,
            ]);

            $this->syncRoles($user, $data['role_ids'], $actor);

            if (! $user->is_active) {
                $this->recordStatus($user, 'active', 'inactive', $actor, 'Created as inactive');
            }

            $this->audit->record('user.created', $user, [], $this->snapshot($user), $actor);

            return $user->load('roles');
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $before = $this->snapshot($user->load('roles'));
            $user->update([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
            ]);

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            if (array_key_exists('role_ids', $data)) {
                $this->ensureAdministratorContinuity($user, $data['role_ids']);
                $this->syncRoles($user, $data['role_ids'], $actor);
            }

            $this->audit->record('user.updated', $user, $before, $this->snapshot($user->fresh('roles')), $actor);

            return $user->fresh('roles');
        });
    }

    public function setActive(User $user, bool $active, User $actor, ?string $reason = null): User
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['status' => 'You cannot change your own account status.']);
        }

        if (! $active) {
            $this->ensureAdministratorContinuity($user, []);
        }

        return DB::transaction(function () use ($user, $active, $actor, $reason) {
            $from = $user->is_active ? 'active' : 'inactive';
            $to = $active ? 'active' : 'inactive';

            if ($from === $to) {
                return $user;
            }

            $user->update([
                'is_active' => $active,
                'deactivated_at' => $active ? null : now(),
                'deactivated_by_user_id' => $active ? null : $actor->id,
                'remember_token' => $active ? $user->remember_token : null,
            ]);

            $this->recordStatus($user, $from, $to, $actor, $reason);
            $this->audit->record(
                $active ? 'user.activated' : 'user.deactivated',
                $user,
                ['status' => $from],
                ['status' => $to, 'reason' => $reason],
                $actor,
            );

            return $user;
        });
    }

    public function archive(User $user, User $actor, ?string $reason = null): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['archive' => 'You cannot archive your own account.']);
        }

        $this->ensureAdministratorContinuity($user, []);

        DB::transaction(function () use ($user, $actor, $reason) {
            $from = $user->is_active ? 'active' : 'inactive';
            $user->forceFill([
                'is_active' => false,
                'deactivated_at' => $user->deactivated_at ?? now(),
                'deactivated_by_user_id' => $actor->id,
                'remember_token' => null,
            ])->save();

            $this->recordStatus($user, $from, 'archived', $actor, $reason);
            $this->audit->record(
                'user.archived',
                $user,
                ['status' => $from],
                ['status' => 'archived', 'reason' => $reason],
                $actor,
            );
            $user->delete();
        });
    }

    private function syncRoles(User $user, array $roleIds, User $actor): void
    {
        $assignedAt = now();
        $sync = collect($roleIds)->mapWithKeys(fn ($roleId) => [
            $roleId => ['assigned_by_user_id' => $actor->id, 'assigned_at' => $assignedAt],
        ])->all();

        $user->roles()->sync($sync);
    }

    private function ensureAdministratorContinuity(User $user, array $newRoleIds): void
    {
        if (! $user->hasRole('administrator')) {
            return;
        }

        $administratorId = Role::query()->where('slug', 'administrator')->value('id');
        $retainsAdministrator = in_array($administratorId, $newRoleIds, true);

        if ($retainsAdministrator) {
            return;
        }

        $otherActiveAdministrators = User::query()
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'administrator'))
            ->exists();

        if (! $otherActiveAdministrators) {
            throw ValidationException::withMessages([
                'role_ids' => 'At least one active Administrator / Business Manager account is required.',
            ]);
        }
    }

    private function recordStatus(User $user, string $from, string $to, User $actor, ?string $reason): void
    {
        UserStatusHistory::query()->create([
            'user_id' => $user->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
            'changed_at' => now(),
        ]);
    }

    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('slug')->sort()->values()->all(),
        ];
    }
}
