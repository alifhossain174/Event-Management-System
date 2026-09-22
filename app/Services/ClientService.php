<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\DocumentLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientService
{
    public function __construct(
        private readonly ClientDataNormalizer $normalizer,
        private readonly ClientDuplicateService $duplicates,
        private readonly AuditService $audit,
        private readonly StatusTransitionService $statuses,
        private readonly BranchScope $branches,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): ClientSaveResult
    {
        $normalized = $this->normalizer->normalize($data);
        $this->ensureBranchAccess($normalized, $actor);
        $duplicates = $this->duplicates->find($normalized);

        $client = DB::transaction(function () use ($normalized, $actor) {
            $client = Client::query()->create($normalized + [
                'status' => 'active',
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            $this->audit->record('client.created', $client, [], $this->snapshot($client), $actor);

            return $client;
        });

        return new ClientSaveResult($client, $duplicates);
    }

    /** @param array<string, mixed> $data */
    public function update(Client $client, array $data, User $actor): ClientSaveResult
    {
        if ($client->status === 'merged') {
            throw ValidationException::withMessages(['client' => 'Merged clients cannot be edited.']);
        }

        $normalized = $this->normalizer->normalize($data);
        $this->ensureBranchAccess($normalized, $actor);
        $duplicates = $this->duplicates->find($normalized, $client);

        DB::transaction(function () use ($client, $normalized, $actor) {
            $locked = Client::query()->lockForUpdate()->findOrFail($client->getKey());
            $before = $this->snapshot($locked);
            $locked->update($normalized + ['updated_by_user_id' => $actor->getKey()]);
            $this->audit->record('client.updated', $locked, $before, $this->snapshot($locked), $actor);
        });

        $client->refresh();

        return new ClientSaveResult($client, $duplicates);
    }

    public function archive(Client $client, User $actor, string $reason): void
    {
        if ($client->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Only active clients can be archived.']);
        }

        DB::transaction(function () use ($client, $actor, $reason) {
            $client->update([
                'archived_at' => now(),
                'archived_by_user_id' => $actor->getKey(),
                'archive_reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->statuses->transition($client, 'archived', $actor, $reason, auditAction: 'client.archived');
        });
    }

    public function reactivate(Client $client, User $actor, ?string $reason = null): void
    {
        if ($client->status !== 'archived') {
            throw ValidationException::withMessages(['status' => 'Only archived clients can be reactivated.']);
        }

        DB::transaction(function () use ($client, $actor, $reason) {
            $client->update([
                'archived_at' => null,
                'archived_by_user_id' => null,
                'archive_reason' => null,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->statuses->transition($client, 'active', $actor, $reason, auditAction: 'client.reactivated');
        });
    }

    public function linkUser(Client $client, ?User $user, User $actor): void
    {
        if ($client->status === 'merged') {
            throw ValidationException::withMessages(['user_id' => 'A merged client cannot be linked to a portal user.']);
        }

        if ($user && (! $user->is_active || $user->trashed())) {
            throw ValidationException::withMessages(['user_id' => 'Only an active user can be linked.']);
        }

        DB::transaction(function () use ($client, $user, $actor) {
            $locked = Client::query()->lockForUpdate()->findOrFail($client->getKey());

            if ($user && Client::query()->where('user_id', $user->getKey())->whereKeyNot($locked->getKey())->exists()) {
                throw ValidationException::withMessages(['user_id' => 'That user is already linked to another client.']);
            }

            $before = $locked->user_id;
            $locked->update(['user_id' => $user?->getKey(), 'updated_by_user_id' => $actor->getKey()]);
            $this->audit->record('client.user_link_changed', $locked,
                ['user_id' => $before], ['user_id' => $user?->getKey()], $actor);
        });

        $client->refresh();
    }

    public function merge(Client $source, Client $target, User $actor, string $reason): void
    {
        if ($source->is($target)) {
            throw ValidationException::withMessages(['target_client_id' => 'A client cannot be merged into itself.']);
        }

        DB::transaction(function () use ($source, $target, $actor, $reason) {
            $locked = Client::query()->whereKey([$source->getKey(), $target->getKey()])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedSource = $locked->get($source->getKey());
            $lockedTarget = $locked->get($target->getKey());

            if (! $lockedSource || ! $lockedTarget || $lockedSource->status === 'merged' || $lockedTarget->status !== 'active') {
                throw ValidationException::withMessages(['target_client_id' => 'Select an active target and a source that has not already been merged.']);
            }

            $movedContacts = ClientContact::withTrashed()->where('client_id', $lockedSource->getKey())->count();
            ClientContact::withTrashed()->where('client_id', $lockedSource->getKey())
                ->update(['client_id' => $lockedTarget->getKey(), 'updated_at' => now()]);

            foreach ($lockedSource->documentLinks()->get() as $link) {
                DocumentLink::query()->firstOrCreate([
                    'document_id' => $link->document_id,
                    'linkable_type' => $lockedTarget->getMorphClass(),
                    'linkable_id' => $lockedTarget->getKey(),
                ], [
                    'relationship' => $link->relationship,
                    'created_by_user_id' => $actor->getKey(),
                    'created_at' => now(),
                ]);
            }

            $lockedSource->update([
                'merged_into_client_id' => $lockedTarget->getKey(),
                'archived_at' => now(),
                'archived_by_user_id' => $actor->getKey(),
                'archive_reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->statuses->transition($lockedSource, 'merged', $actor, $reason,
                ['target_client_id' => $lockedTarget->getKey()], 'client.merged');

            $this->audit->record('client.merge_received', $lockedTarget, [], [
                'source_client_id' => $lockedSource->getKey(),
                'moved_contacts' => $movedContacts,
            ], $actor);
        });

        $source->refresh();
        $target->refresh();
    }

    /** @return array<string, mixed> */
    private function snapshot(Client $client): array
    {
        return [
            'type' => $client->type,
            'display_name' => $client->display_name,
            'branch_id' => $client->branch_id,
            'status' => $client->status,
            'has_primary_email' => filled($client->primary_email),
            'has_primary_phone' => filled($client->primary_phone),
        ];
    }

    /** @param array<string, mixed> $data */
    private function ensureBranchAccess(array $data, User $actor): void
    {
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        if (! $this->branches->permits($actor, $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'You cannot assign a client to that branch.',
            ]);
        }
    }
}
