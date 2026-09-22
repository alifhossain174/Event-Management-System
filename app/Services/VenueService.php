<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VenueService
{
    public function __construct(private readonly AuditService $audit, private readonly BranchScope $branches) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Venue
    {
        $this->ensureBranch($actor, $data['branch_id'] ?? null);

        return DB::transaction(function () use ($data, $actor) {
            $venue = Venue::query()->create($this->normalize($data) + [
                'status' => 'active', 'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('venue.created', $venue, [], $this->snapshot($venue), $actor);

            return $venue;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Venue $venue, array $data, User $actor): Venue
    {
        $this->ensureBranch($actor, $data['branch_id'] ?? null);

        return DB::transaction(function () use ($venue, $data, $actor) {
            $locked = Venue::query()->lockForUpdate()->findOrFail($venue->getKey());
            $before = $this->snapshot($locked);
            $locked->update($this->normalize($data) + ['updated_by_user_id' => $actor->getKey()]);
            $this->audit->record('venue.updated', $locked, $before, $this->snapshot($locked), $actor);

            return $locked->fresh();
        });
    }

    public function changeStatus(Venue $venue, string $action, User $actor, ?string $reason): Venue
    {
        return DB::transaction(function () use ($venue, $action, $actor, $reason) {
            $locked = Venue::query()->lockForUpdate()->findOrFail($venue->getKey());
            $status = $action === 'archive' ? 'archived' : 'active';
            $before = $this->snapshot($locked);
            $locked->update([
                'status' => $status, 'archived_at' => $status === 'archived' ? now() : null,
                'archived_by_user_id' => $status === 'archived' ? $actor->getKey() : null,
                'archive_reason' => $status === 'archived' ? $reason : null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record("venue.{$status}", $locked, $before, $this->snapshot($locked) + ['reason' => $reason], $actor);

            return $locked->fresh();
        });
    }

    private function normalize(array $data): array
    {
        $data['name'] = Str::squish($data['name']);
        $data['normalized_name'] = Str::of($data['name'])->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish()->toString();
        $data['country_code'] = filled($data['country_code'] ?? null) ? mb_strtoupper($data['country_code']) : null;

        return $data;
    }

    private function ensureBranch(User $actor, mixed $branchId): void
    {
        if (! $this->branches->permits($actor, $branchId ? (int) $branchId : null)) {
            throw ValidationException::withMessages(['branch_id' => 'You cannot assign that branch.']);
        }
    }

    private function snapshot(Venue $venue): array
    {
        return ['name' => $venue->name, 'type' => $venue->type, 'status' => $venue->status,
            'branch_id' => $venue->branch_id, 'capacity' => $venue->capacity, 'city' => $venue->city];
    }
}
