<?php

namespace App\Http\Requests\Clients;

final class UpdateClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('client')) ?? false;
    }
}
