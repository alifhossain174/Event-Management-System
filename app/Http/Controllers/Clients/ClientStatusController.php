<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ChangeClientStatusRequest;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;

final class ClientStatusController extends Controller
{
    public function __invoke(ChangeClientStatusRequest $request, Client $client, ClientService $clients): RedirectResponse
    {
        $validated = $request->validated();
        if ($validated['action'] === 'archive') {
            $clients->archive($client, $request->user(), $validated['reason']);
            $message = 'Client archived without deleting relationship history.';
        } else {
            $clients->reactivate($client, $request->user(), $validated['reason'] ?? null);
            $message = 'Client reactivated.';
        }

        return redirect()->route('clients.show', $client)->with('status', $message);
    }
}
