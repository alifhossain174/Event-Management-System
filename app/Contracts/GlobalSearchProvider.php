<?php

namespace App\Contracts;

use App\Data\GlobalSearchResult;
use App\Models\User;
use Illuminate\Support\Collection;

interface GlobalSearchProvider
{
    public function key(): string;

    public function label(): string;

    public function canSearch(User $user): bool;

    /** @return Collection<int, GlobalSearchResult> */
    public function search(User $user, string $term, int $limit): Collection;
}
