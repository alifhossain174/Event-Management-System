<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class NotificationService
{
    public function __construct(private readonly NotificationLinkService $links) {}

    /**
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $data
     */
    public function sendToUsers(iterable $users, array $data, ?User $actor = null): ?InAppNotification
    {
        $recipients = collect($users)->filter(fn (User $user) => $user->is_active && ! $user->trashed())->unique('id')->values();

        return $this->create($recipients->map(fn (User $user) => ['user' => $user, 'role_id' => null]), $data, $actor);
    }

    /**
     * Materialize role targets to users so each user receives an isolated read state.
     *
     * @param  list<string>  $roleSlugs
     * @param  array<string, mixed>  $data
     */
    public function sendToRoles(array $roleSlugs, array $data, ?User $actor = null): ?InAppNotification
    {
        $roles = Role::query()->whereIn('slug', $roleSlugs)->get();
        $roleIds = $roles->pluck('id', 'slug');
        $users = User::query()->where('is_active', true)->whereNull('deleted_at')
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.slug', $roleSlugs))
            ->with(['roles' => fn ($query) => $query->whereIn('roles.slug', $roleSlugs)])
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'role_id' => $roleIds->get($user->roles->first()?->slug),
            ]);

        return $this->create($users, $data, $actor);
    }

    /** @param Collection<int, array{user: User, role_id: int|null}> $recipients */
    private function create(Collection $recipients, array $data, ?User $actor): ?InAppNotification
    {
        if ($recipients->isEmpty()) {
            return null;
        }

        $this->links->assertSafe($data['route_name'] ?? null);

        return DB::transaction(function () use ($recipients, $data, $actor) {
            if ($key = $data['idempotency_key'] ?? null) {
                if ($existing = InAppNotification::query()->where('idempotency_key', $key)->first()) {
                    return $existing;
                }
            }

            /** @var Model|null $source */
            $source = $data['source'] ?? null;
            $notification = InAppNotification::query()->create([
                'type' => $data['type'],
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'route_name' => $data['route_name'] ?? null,
                'route_parameters' => $data['route_parameters'] ?? null,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'event_id' => $data['event_id'] ?? null,
                'created_by_user_id' => $actor?->getKey(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $now = now();
            $notification->recipients()->insert($recipients->map(fn (array $recipient) => [
                'notification_id' => $notification->getKey(),
                'user_id' => $recipient['user']->getKey(),
                'target_role_id' => $recipient['role_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $notification->load('recipients');
        });
    }
}
