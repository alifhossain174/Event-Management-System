<?php

namespace App\Services;

use App\Data\CalendarEntry;
use App\Models\Event;
use App\Models\EventVenueAllocation;
use App\Models\Shift;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\VendorAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CalendarService
{
    public const SOURCES = ['events', 'venue', 'staff', 'vendors', 'tasks'];

    public function __construct(
        private readonly BranchScope $branches,
        private readonly TaskAccessService $tasks,
        private readonly SettingsService $settings,
    ) {}

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, timezone: string} */
    public function window(string $view, ?string $date): array
    {
        $timezone = $this->settings->string('general.timezone');
        $anchor = $date ? CarbonImmutable::parse($date, $timezone) : CarbonImmutable::now($timezone);
        $start = match ($view) {
            'weekly' => $anchor->startOfWeek(),
            'monthly' => $anchor->startOfMonth(),
            default => $anchor->startOfDay(),
        };
        $end = match ($view) {
            'weekly' => $start->addWeek(),
            'monthly' => $start->addMonth(),
            default => $start->addDay(),
        };

        return ['start' => $start, 'end' => $end, 'timezone' => $timezone];
    }

    /** @return Collection<int, CalendarEntry> */
    public function entries(User $user, CarbonImmutable $localStart, CarbonImmutable $localEnd, ?string $source = null, ?int $branchId = null): Collection
    {
        $start = $localStart->utc();
        $end = $localEnd->utc();
        $entries = collect();
        $accepts = fn (string $key): bool => $source === null || $source === '' || $source === $key;

        if ($accepts('events') && $user->hasPermission('events.view')) {
            $query = $this->branches->apply(Event::query(), $user)
                ->whereNull('archived_at')->where('status', '!=', 'cancelled')
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->when($branchId, fn (Builder $query, int $id) => $query->where('branch_id', $id));
            foreach ($query->orderBy('starts_at')->limit(500)->get() as $event) {
                $entries->push(new CalendarEntry('event', $event->id, $event->name, CarbonImmutable::instance($event->starts_at), CarbonImmutable::instance($event->ends_at), route('events.show', $event), $event->reference_number, $event->branch_id));
            }
        }

        if ($accepts('venue') && $user->hasPermission('venues.view')) {
            $query = EventVenueAllocation::query()->with(['event', 'venue', 'space'])
                ->whereIn('status', EventVenueAllocation::ACTIVE_STATUSES)
                ->whereHas('event', fn (Builder $events) => $this->eventWindow($events, $start, $end, 'venue', $branchId))
                ->limit(500);
            foreach ($query->get() as $allocation) {
                if (! $this->branches->permits($user, $allocation->event->branch_id)) {
                    continue;
                }
                $entries->push(new CalendarEntry('venue', $allocation->id, $allocation->venue->name.($allocation->space ? ' · '.$allocation->space->name : ''), CarbonImmutable::instance($allocation->event->starts_at), CarbonImmutable::instance($allocation->event->ends_at), route('events.venue.index', $allocation->event), $allocation->event->reference_number, $allocation->event->branch_id));
            }
        }

        if ($accepts('staff') && ($user->hasPermission('staff.view-work') || $user->hasPermission('staff.view-own-work'))) {
            $staffIds = $user->hasPermission('staff.view-work')
                ? $this->branches->apply(StaffProfile::query(), $user)->pluck('id')
                : StaffProfile::query()->where('user_id', $user->getKey())->pluck('id');

            $shifts = Shift::query()->with(['staff', 'event'])->whereIn('staff_profile_id', $staffIds)
                ->whereIn('status', Shift::ACTIVE_STATUSES)->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->where(fn (Builder $query) => $query->whereNull('event_id')->orWhereHas('event.moduleSettings', fn (Builder $settings) => $settings->where('module_key', 'staff')->where('is_enabled', true)))
                ->limit(500)->get();
            foreach ($shifts as $shift) {
                if ($branchId && $shift->staff->branch_id !== $branchId) {
                    continue;
                }
                $entries->push(new CalendarEntry('shift', $shift->id, $shift->title, CarbonImmutable::instance($shift->starts_at), CarbonImmutable::instance($shift->ends_at), route('staff.operations.index', ['staff' => $shift->staff_profile_id]), $shift->staff->display_name, $shift->staff->branch_id));
            }

            $assignments = StaffAssignment::query()->with(['staff', 'event'])->whereIn('staff_profile_id', $staffIds)
                ->whereIn('status', StaffAssignment::ACTIVE_STATUSES)
                ->where('scheduled_starts_at', '<', $end)->where('scheduled_ends_at', '>', $start)
                ->whereHas('event.moduleSettings', fn (Builder $settings) => $settings->where('module_key', 'staff')->where('is_enabled', true))
                ->when($branchId, fn (Builder $query, int $id) => $query->whereHas('event', fn (Builder $events) => $events->where('branch_id', $id)))
                ->limit(500)->get();
            foreach ($assignments as $assignment) {
                $entries->push(new CalendarEntry('staff_assignment', $assignment->id, $assignment->role_title, CarbonImmutable::instance($assignment->scheduled_starts_at), CarbonImmutable::instance($assignment->scheduled_ends_at), route('events.staff.index', $assignment->event), $assignment->staff->display_name.' · '.$assignment->event->reference_number, $assignment->event->branch_id));
            }
        }

        if ($accepts('vendors') && $user->hasPermission('vendors.view-work')) {
            $query = VendorAssignment::query()->with(['vendor', 'event'])
                ->whereIn('status', VendorAssignment::ACTIVE_STATUSES)
                ->where('scheduled_starts_at', '<', $end)->where('scheduled_ends_at', '>', $start)
                ->whereHas('event.moduleSettings', fn (Builder $settings) => $settings->where('module_key', 'vendors')->where('is_enabled', true))
                ->when(! $user->isAdministrator() && ! $user->hasPermission('vendors.assign'), fn (Builder $query) => $query->whereHas('vendor', fn (Builder $vendors) => $vendors->where('user_id', $user->getKey())))
                ->when($branchId, fn (Builder $query, int $id) => $query->whereHas('event', fn (Builder $events) => $events->where('branch_id', $id)))
                ->limit(500);
            foreach ($query->get() as $assignment) {
                if (! $this->branches->permits($user, $assignment->event->branch_id)) {
                    continue;
                }
                $entries->push(new CalendarEntry('vendor_assignment', $assignment->id, $assignment->vendor->display_name, CarbonImmutable::instance($assignment->scheduled_starts_at), CarbonImmutable::instance($assignment->scheduled_ends_at), route('events.vendors.show', [$assignment->event, $assignment]), $assignment->event->reference_number, $assignment->event->branch_id));
            }
        }

        if ($accepts('tasks') && ($user->hasPermission('tasks.view') || $user->hasPermission('tasks.view-assigned'))) {
            $query = $this->tasks->visibleTo($user)->with('event')
                ->whereNull('archived_at')->whereNotNull('due_at')
                ->where('due_at', '>=', $start)->where('due_at', '<', $end)
                ->when($branchId, fn (Builder $query, int $id) => $query->whereHas('event', fn (Builder $events) => $events->where('branch_id', $id)))
                ->limit(500);
            foreach ($query->get() as $task) {
                $entries->push(new CalendarEntry('task', $task->id, $task->title, CarbonImmutable::instance($task->due_at), null, route('events.tasks.show', [$task->event, $task]), $task->event->reference_number, $task->event->branch_id));
            }
        }

        return $entries->unique(fn (CalendarEntry $entry) => $entry->key())->sortBy(fn (CalendarEntry $entry) => $entry->startsAt->getTimestamp())->values();
    }

    private function eventWindow(Builder $query, CarbonImmutable $start, CarbonImmutable $end, string $moduleKey, ?int $branchId): Builder
    {
        return $query->whereNull('archived_at')->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->whereHas('moduleSettings', fn (Builder $settings) => $settings->where('module_key', $moduleKey)->where('is_enabled', true))
            ->when($branchId, fn (Builder $events, int $id) => $events->where('branch_id', $id));
    }
}
