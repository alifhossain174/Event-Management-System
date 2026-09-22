<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BranchScope
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->settings->boolean('features.branches_enabled');
    }

    public function apply(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        if (! $this->enabled() || ! $user || $user->isAdministrator()) {
            return $query;
        }

        $branchIds = $user->branches()->pluck('branches.id');

        return $query->where(function (Builder $query) use ($column, $branchIds) {
            $query->whereNull($column);

            if ($branchIds->isNotEmpty()) {
                $query->orWhereIn($column, $branchIds);
            }
        });
    }

    public function permits(?User $user, ?int $branchId): bool
    {
        if (! $this->enabled() || $branchId === null || $user?->isAdministrator()) {
            return true;
        }

        return $user?->branches()->whereKey($branchId)->exists() ?? false;
    }

    /** @return Collection<int, Branch> */
    public function optionsFor(User $user): Collection
    {
        if ($this->enabled() && ! $user->isAdministrator()) {
            return $user->branches()->where('is_active', true)->orderBy('name')->get();
        }

        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }
}
