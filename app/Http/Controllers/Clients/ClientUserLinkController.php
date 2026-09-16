<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\LinkClientUserRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;

final class ClientUserLinkController extends Controller
{
    public function __invoke(LinkClientUserRequest $request, Client $client, ClientService $clients): RedirectResponse
    {
        $user = $request->validated('user_id') ? User::query()->findOrFail($request->validated('user_id')) : null;
        $clients->linkUser($client, $user, $request->user());

        return back()->with('status', $user ? 'Portal user linked.' : 'Portal user link removed.');
    }
}
