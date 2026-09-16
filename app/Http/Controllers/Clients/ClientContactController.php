<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\ClientContactService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientContactController extends Controller
{
    public function store(ClientContactRequest $request, Client $client, ClientContactService $contacts): RedirectResponse
    {
        $contacts->create($client, $request->validated(), $request->user());

        return back()->with('status', 'Contact added.');
    }

    public function edit(Client $client, ClientContact $contact): View
    {
        Gate::authorize('update', $client);
        abort_unless($contact->client_id === $client->getKey(), 404);

        return view('clients.contacts.edit', compact('client', 'contact'));
    }

    public function update(ClientContactRequest $request, Client $client, ClientContact $contact, ClientContactService $contacts): RedirectResponse
    {
        $contacts->update($client, $contact, $request->validated(), $request->user());

        return redirect()->route('clients.show', $client)->with('status', 'Contact updated.');
    }

    public function destroy(Request $request, Client $client, ClientContact $contact, ClientContactService $contacts): RedirectResponse
    {
        Gate::authorize('update', $client);
        $contacts->archive($client, $contact, $request->user());

        return back()->with('status', 'Contact archived.');
    }
}
