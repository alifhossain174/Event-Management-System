<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EventQueryService
{
    public function __construct(
        private readonly BranchScope $branches,
        private readonly SettingsService $settings,
    ) {}

    public function visibleTo(User $user): Builder
    {
        return $this->branches->apply(Event::query(), $user);
    }

    /** @param array<string, mixed> $filters */
    public function applyDashboardFilters(Builder $query, array $filters): Builder
    {
        $timezone = ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null)
            ? $this->settings->string('general.timezone')
            : 'UTC';

        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->where('starts_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->utc()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->where('starts_at', '<=', CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->utc()))
            ->when($filters['branch'] ?? null, fn (Builder $query, int|string $branch) => $query->where('branch_id', $branch))
            ->when($filters['category'] ?? null, fn (Builder $query, int|string $category) => $query->where('event_category_id', $category))
            ->when($filters['manager'] ?? null, fn (Builder $query, int|string $manager) => $query->where('manager_user_id', $manager));
    }

    public function applyListScope(Builder $query, ?string $scope): Builder
    {
        return match ($scope) {
            'upcoming' => $query->where('starts_at', '>=', now()->utc())
                ->whereNotIn('status', ['completed', 'cancelled']),
            default => $query,
        };
    }

    /** @return Collection<int, User> */
    public function managerOptions(User $user): Collection
    {
        $eventManagers = $this->visibleTo($user)
            ->whereNull('archived_at')
            ->whereNotNull('manager_user_id')
            ->select('manager_user_id');

        return User::query()->whereIn('id', $eventManagers)->where('is_active', true)
            ->orderBy('name')->limit(200)->get(['id', 'name']);
    }
}
