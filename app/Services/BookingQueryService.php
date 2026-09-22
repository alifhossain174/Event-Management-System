<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class BookingQueryService
{
    public function __construct(
        private readonly BranchScope $branches,
        private readonly SettingsService $settings,
    ) {}

    public function visibleTo(User $user): Builder
    {
        return $this->branches->apply(Booking::query(), $user);
    }

    /** @param array<string, mixed> $filters */
    public function applyDashboardFilters(Builder $query, array $filters): Builder
    {
        $timezone = ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null)
            ? $this->settings->string('general.timezone')
            : 'UTC';

        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->where('requested_starts_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->utc()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->where('requested_starts_at', '<=', CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->utc()))
            ->when($filters['branch'] ?? null, fn (Builder $query, int|string $branch) => $query->where('branch_id', $branch))
            ->when($filters['category'] ?? null, fn (Builder $query, int|string $category) => $query->where('requested_event_category_id', $category));
    }
}
