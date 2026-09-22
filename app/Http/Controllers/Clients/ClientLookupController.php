<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientLookupController extends Controller
{
    public function __invoke(Request $request, BranchScope $branches): JsonResponse
    {
        Gate::authorize('viewAny', Client::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $query = $branches->apply(Client::query(), $request->user());

        return response()->json(['data' => $query->where('status', 'active')
            ->search($filters['q'] ?? null)->orderBy('display_name')->limit(20)->get()
            ->map(fn (Client $client) => [
                'id' => $client->getKey(),
                'text' => $client->display_name,
                'type' => $client->type,
                'email' => $client->primary_email,
                'phone' => $client->primary_phone,
            ])]);
    }
}
