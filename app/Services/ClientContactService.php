<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ClientContactService
{
    public function __construct(
        private readonly ClientDataNormalizer $normalizer,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Client $client, array $data, User $actor): ClientContact
    {
        return DB::transaction(function () use ($client, $data, $actor) {
            $normalized = $this->normalize($data);
            if ($normalized['is_primary']) {
                $client->contacts()->update(['is_primary' => false]);
            }
            $contact = $client->contacts()->create($normalized);
            $this->audit->record('client.contact_created', $client, [], $this->snapshot($contact), $actor);

            return $contact;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Client $client, ClientContact $contact, array $data, User $actor): void
    {
        abort_unless($contact->client_id === $client->getKey(), 404);

        DB::transaction(function () use ($client, $contact, $data, $actor) {
            $before = $this->snapshot($contact);
            $normalized = $this->normalize($data);
            if ($normalized['is_primary']) {
                $client->contacts()->whereKeyNot($contact->getKey())->update(['is_primary' => false]);
            }
            $contact->update($normalized);
            $this->audit->record('client.contact_updated', $client, $before, $this->snapshot($contact), $actor);
        });
    }

    public function archive(Client $client, ClientContact $contact, User $actor): void
    {
        abort_unless($contact->client_id === $client->getKey(), 404);
        DB::transaction(function () use ($client, $contact, $actor) {
            $before = $this->snapshot($contact);
            $contact->delete();
            $this->audit->record('client.contact_archived', $client, $before, [], $actor);
        });
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data): array
    {
        $data['normalized_email'] = $this->normalizer->email($data['email'] ?? null);
        $data['normalized_phone'] = $this->normalizer->phone($data['phone'] ?? null);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        return $data;
    }

    /** @return array<string, mixed> */
    private function snapshot(ClientContact $contact): array
    {
        return [
            'contact_id' => $contact->getKey(),
            'name' => $contact->name,
            'relationship_label' => $contact->relationship_label,
            'is_primary' => $contact->is_primary,
            'has_email' => filled($contact->email),
            'has_phone' => filled($contact->phone),
        ];
    }
}
