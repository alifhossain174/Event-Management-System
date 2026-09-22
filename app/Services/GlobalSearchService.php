<?php

namespace App\Services;

use App\Contracts\GlobalSearchProvider;
use App\Models\User;
use Illuminate\Support\Collection;

final class GlobalSearchService
{
    /** @param iterable<GlobalSearchProvider> $providers */
    public function __construct(private readonly iterable $providers) {}

    public function minimumLength(): int
    {
        return (int) config('global-search.minimum_length', 2);
    }

    public function normalize(?string $term): string
    {
        $term = preg_replace('/[%_\\\\]+/u', ' ', (string) $term) ?? '';

        return str(preg_replace('/\s+/u', ' ', trim($term)) ?? '')->limit(100, '')->toString();
    }

    /** @return Collection<int, array{key: string, label: string, results: Collection}> */
    public function search(User $user, ?string $term): Collection
    {
        $term = $this->normalize($term);
        if (mb_strlen($term) < $this->minimumLength()) {
            return collect();
        }

        $limit = max(1, min((int) config('global-search.per_provider_limit', 8), 20));

        return collect($this->providers)
            ->filter(fn (GlobalSearchProvider $provider) => $provider->canSearch($user))
            ->map(fn (GlobalSearchProvider $provider) => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'results' => $provider->search($user, $term, $limit),
            ])
            ->filter(fn (array $group) => $group['results']->isNotEmpty())
            ->values();
    }
}
