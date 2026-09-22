<?php

namespace App\Services;

use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class NotificationInboxService
{
    public function unreadCount(User $user): int
    {
        return NotificationRecipient::query()->where('user_id', $user->getKey())->whereNull('read_at')->count();
    }

    public function recent(User $user, int $limit = 5)
    {
        return NotificationRecipient::query()->where('user_id', $user->getKey())
            ->with('notification')->latest()->limit($limit)->get();
    }

    public function paginate(User $user, ?string $state = null): LengthAwarePaginator
    {
        return NotificationRecipient::query()->where('user_id', $user->getKey())
            ->with('notification')
            ->when($state === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($state === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()->paginate(20)->withQueryString();
    }
}
