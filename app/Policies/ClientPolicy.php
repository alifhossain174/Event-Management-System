<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Services\BranchScope;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.view') && app(BranchScope::class)->permits($user, $client->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $client->status !== 'merged' && $user->hasPermission('clients.update') && $this->view($user, $client);
    }

    public function archive(User $user, Client $client): bool
    {
        return $client->status === 'active' && $user->hasPermission('clients.delete') && $this->view($user, $client);
    }

    public function reactivate(User $user, Client $client): bool
    {
        return $client->status === 'archived' && $user->hasPermission('clients.delete') && $this->view($user, $client);
    }

    public function assignUser(User $user, Client $client): bool
    {
        return $client->status !== 'merged' && $user->hasPermission('clients.assign') && $this->view($user, $client);
    }

    public function merge(User $user, Client $client): bool
    {
        return $client->status !== 'merged' && $user->hasPermission('clients.merge') && $this->view($user, $client);
    }
}
