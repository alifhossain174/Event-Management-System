<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;

final class StoreClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }
}
