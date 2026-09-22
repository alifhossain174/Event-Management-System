<?php

namespace App\Contracts;

use App\Data\ApprovedVendorCost;
use App\Models\Event;
use Illuminate\Support\Collection;

interface ApprovedVendorCostProvider
{
    /** @return Collection<int, ApprovedVendorCost> */
    public function forEvent(Event $event): Collection;
}
