<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\MergeClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;

final class MergeClientController extends Controller
{
    public function __invoke(MergeClientRequest $request, Client $client, ClientService $clients): RedirectResponse
    {
        $target = Client::query()->findOrFail($request->integer('target_client_id'));
        $clients->merge($client, $target, $request->user(), $request->string('reason')->toString());

        return redirect()->route('clients.show', $target)->with('status', 'Duplicate client merged; source history was preserved.');
    }
}
