<?php

namespace App\Search;

use App\Contracts\GlobalSearchProvider;
use App\Data\GlobalSearchResult;
use App\Models\User;
use App\Services\BookingQueryService;
use Illuminate\Support\Collection;

final class BookingSearchProvider implements GlobalSearchProvider
{
    public function __construct(private readonly BookingQueryService $bookings) {}

    public function key(): string
    {
        return 'bookings';
    }

    public function label(): string
    {
        return 'Bookings';
    }

    public function canSearch(User $user): bool
    {
        return $user->hasPermission('bookings.view');
    }

    public function search(User $user, string $term, int $limit): Collection
    {
        return $this->bookings->visibleTo($user)
            ->with('client:id,display_name')
            ->where(fn ($query) => $query->where('reference_number', 'like', "{$term}%")
                ->orWhere('venue_preference', 'like', "{$term}%")
                ->orWhereHas('client', fn ($clients) => $clients->where('normalized_name', 'like', mb_strtolower($term).'%')))
            ->orderByDesc('requested_starts_at')->limit($limit)->get()
            ->map(fn ($booking) => new GlobalSearchResult(
                $booking->reference_number,
                route('bookings.show', $booking),
                trim(($booking->client?->display_name ?? 'No client').' · '.($booking->venue_preference ?? 'Venue not set')),
                str($booking->status)->headline()->toString(),
            ));
    }
}
