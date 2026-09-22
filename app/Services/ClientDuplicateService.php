<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

final class ClientDuplicateService
{
    /** @param array<string, mixed> $normalized */
    public function find(array $normalized, ?Client $exclude = null): Collection
    {
        $email = $normalized['normalized_email'] ?? null;
        $phone = $normalized['normalized_phone'] ?? null;
        $name = $normalized['normalized_name'] ?? null;

        if (! $email && ! $phone && ! $name) {
            return new Collection;
        }

        return Client::query()
            ->where('status', '!=', 'merged')
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude->getKey()))
            ->where(function ($query) use ($email, $phone, $name) {
                $hasCondition = false;

                if ($email) {
                    $query->where('normalized_email', $email);
                    $hasCondition = true;
                }
                if ($phone) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $query->{$method}('normalized_phone', $phone);
                    $hasCondition = true;
                }
                if ($name) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $query->{$method}('normalized_name', $name);
                }
            })
            ->orderBy('display_name')
            ->limit(10)
            ->get();
    }
}
