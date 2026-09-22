<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

final readonly class ClientSaveResult
{
    public function __construct(public Client $client, public Collection $duplicates) {}
}
