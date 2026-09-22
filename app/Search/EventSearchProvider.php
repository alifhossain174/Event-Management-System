<?php

namespace App\Search;

use App\Contracts\GlobalSearchProvider;
use App\Data\GlobalSearchResult;
use App\Models\User;
use App\Services\EventQueryService;
use Illuminate\Support\Collection;

final class EventSearchProvider implements GlobalSearchProvider
{
    public function __construct(private readonly EventQueryService $events) {}

    public function key(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function canSearch(User $user): bool
    {
        return $user->hasPermission('events.view');
    }

    public function search(User $user, string $term, int $limit): Collection
    {
        return $this->events->visibleTo($user)->whereNull('archived_at')
            ->with('client:id,display_name')
            ->where(fn ($query) => $query->where('reference_number', 'like', "{$term}%")
                ->orWhere('name', 'like', "{$term}%")
                ->orWhereHas('client', fn ($clients) => $clients->where('normalized_name', 'like', mb_strtolower($term).'%')))
            ->orderByDesc('starts_at')->limit($limit)->get()
            ->map(fn ($event) => new GlobalSearchResult(
                $event->name,
                route('events.show', $event),
                trim($event->reference_number.' · '.($event->client?->display_name ?? 'No client')),
                str($event->status)->headline()->toString(),
            ));
    }
}
