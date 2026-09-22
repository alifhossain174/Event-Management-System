<?php

namespace App\Services;

use App\Models\EventCategory;
use App\Models\User;

final class DashboardService
{
    public function __construct(
        private readonly EventQueryService $events,
        private readonly BookingQueryService $bookings,
        private readonly BranchScope $branches,
        private readonly SettingsService $settings,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function for(User $user, array $filters): array
    {
        $canViewEvents = $user->hasPermission('events.view');
        $metrics = collect();
        $upcoming = collect();
        $canViewBookings = $user->hasPermission('bookings.view');
        $latestBookings = collect();

        if ($canViewEvents) {
            $base = $this->events->applyDashboardFilters(
                $this->events->visibleTo($user)->whereNull('archived_at'),
                $filters,
            );
            $now = now()->utc();
            $row = (clone $base)->selectRaw(
                'COUNT(*) AS total_count, '.
                'SUM(CASE WHEN starts_at >= ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) AS upcoming_count, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS ongoing_count, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_count, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled_count',
                [$now, 'completed', 'cancelled', 'in_progress', 'completed', 'cancelled'],
            )->first();

            $metrics = collect([
                ['key' => 'total', 'label' => 'Total events', 'count' => (int) $row->total_count, 'parameters' => []],
                ['key' => 'upcoming', 'label' => 'Upcoming', 'count' => (int) $row->upcoming_count, 'parameters' => ['view' => 'upcoming']],
                ['key' => 'ongoing', 'label' => 'Ongoing', 'count' => (int) $row->ongoing_count, 'parameters' => ['status' => 'in_progress']],
                ['key' => 'completed', 'label' => 'Completed', 'count' => (int) $row->completed_count, 'parameters' => ['status' => 'completed']],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => (int) $row->cancelled_count, 'parameters' => ['status' => 'cancelled']],
            ])->map(fn (array $metric) => $metric + [
                'url' => route('events.index', array_filter($filters) + $metric['parameters']),
            ]);

            if ((int) $row->upcoming_count > 0) {
                $upcoming = (clone $base)->with(['client:id,display_name', 'category:id,name'])
                    ->where('starts_at', '>=', $now)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->orderBy('starts_at')
                    ->limit(5)
                    ->get();
            }
        }

        if ($canViewBookings) {
            $latestBookings = $this->bookings->applyDashboardFilters(
                $this->bookings->visibleTo($user),
                $filters,
            )->with(['client:id,display_name', 'category:id,name'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        return [
            'canViewEvents' => $canViewEvents,
            'filters' => $filters,
            'metrics' => $metrics,
            'upcomingEvents' => $upcoming,
            'canViewBookings' => $canViewBookings,
            'latestBookings' => $latestBookings,
            'categories' => $canViewEvents ? EventCategory::query()->where('is_active', true)->orderBy('name')->get() : collect(),
            'branches' => $canViewEvents ? $this->branches->optionsFor($user) : collect(),
            'managers' => $canViewEvents ? $this->events->managerOptions($user) : collect(),
            'organizationTimezone' => $this->settings->string('general.timezone'),
        ];
    }
}
