<?php

namespace App\Search;

use App\Contracts\GlobalSearchProvider;
use App\Data\GlobalSearchResult;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BranchScope;
use Illuminate\Support\Collection;

final class VendorSearchProvider implements GlobalSearchProvider
{
    public function __construct(private readonly BranchScope $branches) {}

    public function key(): string
    {
        return 'vendors';
    }

    public function label(): string
    {
        return 'Vendors';
    }

    public function canSearch(User $user): bool
    {
        return $user->hasPermission('vendors.view') || $user->hasPermission('vendors.view-own');
    }

    public function search(User $user, string $term, int $limit): Collection
    {
        $query = $user->hasPermission('vendors.view')
            ? $this->branches->apply(Vendor::query(), $user)
            : Vendor::query()->where('user_id', $user->id);
        $normalized = mb_strtolower($term);

        return $query->where('status', 'active')
            ->where(fn ($query) => $query->where('normalized_name', 'like', "{$normalized}%")
                ->orWhere('normalized_email', 'like', "{$normalized}%")
                ->orWhere('normalized_phone', 'like', "{$term}%"))
            ->orderBy('display_name')->limit($limit)->get()
            ->map(fn ($vendor) => new GlobalSearchResult(
                $vendor->display_name,
                route('vendors.show', $vendor),
                $vendor->primary_email,
                'Vendor',
            ));
    }
}
