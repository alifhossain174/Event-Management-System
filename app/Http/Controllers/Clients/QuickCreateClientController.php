<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;

final class QuickCreateClientController extends Controller
{
    public function __invoke(StoreClientRequest $request, ClientService $clients): JsonResponse
    {
        $result = $clients->create($request->validated(), $request->user());

        return response()->json([
            'data' => ['id' => $result->client->getKey(), 'text' => $result->client->display_name],
            'warnings' => $result->duplicates->map(fn ($client) => [
                'id' => $client->getKey(), 'text' => $client->display_name,
            ])->values(),
        ], 201);
    }
}
