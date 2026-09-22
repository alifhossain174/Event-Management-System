<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\Event;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;

final class DocumentAccessService
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }

        if (! $user->hasPermission('documents.view')) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = $user->branches()->pluck('branches.id');

        return $query->where(function (Builder $query) use ($user, $branchIds) {
            $query->where('uploaded_by_user_id', $user->getKey())
                ->orWhereHas('links', fn (Builder $links) => $links
                    ->where('linkable_type', $user->getMorphClass())
                    ->where('linkable_id', $user->getKey()));

            if ($branchIds->isNotEmpty()) {
                $query->orWhereIn('branch_id', $branchIds)
                    ->orWhereHas('links', fn (Builder $links) => $links
                        ->where('linkable_type', (new Branch)->getMorphClass())
                        ->whereIn('linkable_id', $branchIds));
            }

            $query->orWhereHas('links', fn (Builder $links) => $links
                ->where('linkable_type', (new Client)->getMorphClass())
                ->whereIn('linkable_id', Client::query()->where('user_id', $user->getKey())->select('id')));

            if ($user->hasPermission('events.view')) {
                $eventQuery = app(BranchScope::class)->apply(Event::query(), $user)->select('id');
                $query->orWhereHas('links', fn (Builder $links) => $links
                    ->where('linkable_type', (new Event)->getMorphClass())
                    ->whereIn('linkable_id', $eventQuery));
            }

            if ($user->hasPermission('bookings.view')) {
                $bookingQuery = app(BookingQueryService::class)->visibleTo($user)->select('id');
                $query->orWhereHas('links', fn (Builder $links) => $links
                    ->where('linkable_type', (new Booking)->getMorphClass())
                    ->whereIn('linkable_id', $bookingQuery));
            }

            if ($user->hasPermission('venues.view')) {
                $venueQuery = app(BranchScope::class)->apply(Venue::query(), $user)->select('id');
                $query->orWhereHas('links', fn (Builder $links) => $links
                    ->where('linkable_type', (new Venue)->getMorphClass())
                    ->whereIn('linkable_id', $venueQuery));
            }

            if ($user->hasPermission('tasks.view') || $user->hasPermission('tasks.view-assigned')) {
                $taskQuery = app(TaskAccessService::class)->visibleTo($user)->select('id');
                $query->orWhereHas('links', fn (Builder $links) => $links
                    ->where('linkable_type', (new Task)->getMorphClass())
                    ->whereIn('linkable_id', $taskQuery));
            }

            $query->orWhereHas('links', fn (Builder $links) => $links
                ->where('linkable_type', (new Vendor)->getMorphClass())
                ->whereIn('linkable_id', Vendor::query()->where('user_id', $user->getKey())->select('id')))
                ->orWhereHas('links', fn (Builder $links) => $links
                    ->where('linkable_type', (new StaffProfile)->getMorphClass())
                    ->whereIn('linkable_id', StaffProfile::query()->where('user_id', $user->getKey())->select('id')));
        });
    }

    public function canView(User $user, Document $document): bool
    {
        return $this->scopeVisibleTo(Document::query()->whereKey($document->getKey()), $user)->exists();
    }
}
