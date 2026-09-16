<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\User;
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
        });
    }

    public function canView(User $user, Document $document): bool
    {
        return $this->scopeVisibleTo(Document::query()->whereKey($document->getKey()), $user)->exists();
    }
}
