<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\ClientDuplicateService;
use App\Services\ClientService;
use App\Services\ClientSummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientController extends Controller
{
    public function index(Request $request, BranchScope $branches): View
    {
        Gate::authorize('viewAny', Client::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:individual,organization'],
            'status' => ['nullable', 'in:active,archived,merged'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $query = $branches->apply(Client::query(), $request->user());
        $clients = $query->with(['branch', 'user'])
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status), fn ($query) => $query->where('status', 'active'))
            ->when($filters['branch'] ?? null, fn ($query, $branch) => $query->where('branch_id', $branch))
            ->orderBy('display_name')->paginate(20)->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'filters' => $filters,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.create', ['branches' => $this->branches()]);
    }

    public function store(StoreClientRequest $request, ClientService $clients): RedirectResponse
    {
        $result = $clients->create($request->validated(), $request->user());
        $response = redirect()->route('clients.show', $result->client)->with('status', 'Client created.');

        if ($result->duplicates->isNotEmpty()) {
            $response->with('warning', 'Possible duplicate clients: '.$result->duplicates->pluck('display_name')->join(', ').'. The new record was saved for review.');
        }

        return $response;
    }

    public function show(
        Client $client,
        ClientSummaryService $summary,
        ClientDuplicateService $duplicates,
    ): View {
        Gate::authorize('view', $client);
        $client->load(['contacts', 'user', 'branch', 'statusHistory.actor', 'mergedInto', 'documentLinks.document.currentVersion']);

        return view('clients.show', [
            'client' => $client,
            'summary' => $summary->for($client),
            'duplicates' => $duplicates->find($client->getAttributes(), $client),
            'mergeTargets' => Client::query()->where('status', 'active')->whereKeyNot($client->getKey())->orderBy('display_name')->limit(100)->get(),
            'portalUsers' => User::query()->where('is_active', true)->whereNull('deleted_at')
                ->whereHas('roles', fn ($query) => $query->where('slug', 'client'))
                ->where(fn ($query) => $query->whereDoesntHave('clientProfile')
                    ->when($client->user_id, fn ($query, $userId) => $query->orWhere('users.id', $userId)))
                ->orderBy('name')->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.edit', ['client' => $client, 'branches' => $this->branches()]);
    }

    public function update(UpdateClientRequest $request, Client $client, ClientService $clients): RedirectResponse
    {
        $result = $clients->update($client, $request->validated(), $request->user());
        $response = redirect()->route('clients.show', $result->client)->with('status', 'Client updated.');

        if ($result->duplicates->isNotEmpty()) {
            $response->with('warning', 'Possible duplicate clients: '.$result->duplicates->pluck('display_name')->join(', ').'. Review before merging.');
        }

        return $response;
    }

    /** @return Collection<int, Branch> */
    private function branches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get();
    }
}
