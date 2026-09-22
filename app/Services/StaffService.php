<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StaffService
{
    public function __construct(private readonly AuditService $audit, private readonly StatusTransitionService $statuses, private readonly BranchScope $branches) {}

    public function create(array $data, User $actor): StaffProfile
    {
        $this->ensureBranch($actor, $data['branch_id'] ?? null);

        return DB::transaction(function () use ($data, $actor) {
            $profile = StaffProfile::query()->create($this->normalize($data) + ['record_status' => 'active', 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
            $this->audit->record('staff.created', $profile, [], $this->snapshot($profile), $actor);

            return $profile;
        });
    }

    public function update(StaffProfile $profile, array $data, User $actor): StaffProfile
    {
        $this->ensureBranch($actor, $data['branch_id'] ?? null);

        return DB::transaction(function () use ($profile, $data, $actor) {
            $locked = StaffProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $before = $this->snapshot($locked);
            $locked->update($this->normalize($data) + ['updated_by_user_id' => $actor->id]);
            $this->audit->record('staff.updated', $locked, $before, $this->snapshot($locked), $actor);

            return $locked->fresh();
        });
    }

    public function changeStatus(StaffProfile $profile, string $action, User $actor, ?string $reason): void
    {
        $target = $action === 'archive' ? 'archived' : 'active';
        DB::transaction(function () use ($profile, $target, $actor, $reason) {
            $profile->update(['archived_at' => $target === 'archived' ? now() : null, 'archived_by_user_id' => $target === 'archived' ? $actor->id : null, 'archive_reason' => $target === 'archived' ? $reason : null, 'updated_by_user_id' => $actor->id]);
            $this->statuses->transition($profile, $target, $actor, $reason, auditAction: "staff.{$target}");
        });
    }

    public function linkUser(StaffProfile $profile, ?User $user, User $actor): void
    {
        if ($user && (! $user->is_active || $user->trashed() || ! $user->hasRole('staff'))) {
            throw ValidationException::withMessages(['user_id' => 'Select an active staff-role user.']);
        }
        DB::transaction(function () use ($profile, $user, $actor) {
            $locked = StaffProfile::query()->lockForUpdate()->findOrFail($profile->id);
            if ($user && StaffProfile::query()->where('user_id', $user->id)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'That user is already linked to another staff profile.']);
            } $before = $locked->user_id;
            $locked->update(['user_id' => $user?->id, 'updated_by_user_id' => $actor->id]);
            $this->audit->record('staff.user_link_changed', $locked, ['user_id' => $before], ['user_id' => $user?->id], $actor);
        });
    }

    private function normalize(array $data): array
    {
        $name = Str::squish(collect([$data['first_name'], $data['last_name'] ?? null])->filter()->join(' '));
        $data['display_name'] = $name;
        $data['normalized_name'] = Str::lower($name);
        $data['normalized_email'] = filled($data['email'] ?? null) ? mb_strtolower($data['email']) : null;
        $data['normalized_phone'] = filled($data['phone'] ?? null) ? preg_replace('/\D+/', '', $data['phone']) : null;

        return $data;
    }

    private function snapshot(StaffProfile $profile): array
    {
        return ['display_name' => $profile->display_name, 'branch_id' => $profile->branch_id, 'department_id' => $profile->department_id, 'employment_status' => $profile->employment_status, 'record_status' => $profile->record_status];
    }

    private function ensureBranch(User $actor, mixed $branch): void
    {
        if (! $this->branches->permits($actor, $branch ? (int) $branch : null)) {
            throw ValidationException::withMessages(['branch_id' => 'You cannot assign that branch.']);
        }
    }
}
