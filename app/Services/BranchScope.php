<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
}
