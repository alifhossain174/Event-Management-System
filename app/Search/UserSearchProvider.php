<?php

namespace App\Search;

use App\Contracts\GlobalSearchProvider;
use App\Data\GlobalSearchResult;
use App\Models\User;
use Illuminate\Support\Collection;

final class UserSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    public function canSearch(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function search(User $user, string $term, int $limit): Collection
    {
        $normalized = mb_strtolower($term);

        return User::query()->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('name', 'like', "{$term}%")
                ->orWhere('email', 'like', "{$normalized}%"))
            ->orderBy('name')->limit($limit)->get()
            ->map(fn ($result) => new GlobalSearchResult(
                $result->name,
                route('users.show', $result),
                $result->email,
                $result->is_active ? 'Active' : 'Inactive',
            ));
    }
}
