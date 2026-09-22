<?php

namespace App\Search;

use App\Contracts\GlobalSearchProvider;
use App\Data\GlobalSearchResult;
use App\Models\Client;
use App\Models\User;
use App\Services\BranchScope;
use Illuminate\Support\Collection;

final class ClientSearchProvider implements GlobalSearchProvider
{
    public function __construct(private readonly BranchScope $branches) {}

    public function key(): string
    {
        return 'clients';
    }

    public function label(): string
    {
        return 'Clients';
    }

    public function canSearch(User $user): bool
    {
        return $user->hasPermission('clients.view');
    }

    public function search(User $user, string $term, int $limit): Collection
    {
        $normalized = mb_strtolower($term);

        return $this->branches->apply(Client::query(), $user)
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('normalized_name', 'like', "{$normalized}%")
                ->orWhere('normalized_email', 'like', "{$normalized}%")
                ->orWhere('normalized_phone', 'like', "{$term}%"))
            ->orderBy('display_name')->limit($limit)->get()
            ->map(fn ($client) => new GlobalSearchResult(
                $client->display_name,
                route('clients.show', $client),
                $client->primary_email ?: str($client->type)->headline()->toString(),
                str($client->type)->headline()->toString(),
            ));
    }
}
