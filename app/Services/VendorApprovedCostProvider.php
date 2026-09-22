<?php

namespace App\Services;

use App\Contracts\ApprovedVendorCostProvider;
use App\Data\ApprovedVendorCost;
use App\Models\Event;
use App\Models\VendorAssignment;
use Illuminate\Support\Collection;

final class VendorApprovedCostProvider implements ApprovedVendorCostProvider
{
    public function forEvent(Event $event): Collection
    {
        return VendorAssignment::query()
            ->where('event_id', $event->getKey())
            ->whereNotNull('approved_cost')
            ->whereIn('status', ['approved', 'in_progress', 'completed'])
            ->with('vendor:id,display_name')
            ->orderBy('id')
            ->get()
            ->map(fn (VendorAssignment $assignment) => new ApprovedVendorCost(
                sourceId: $assignment->getKey(),
                eventId: $assignment->event_id,
                vendorId: $assignment->vendor_id,
                description: $assignment->vendor->display_name.' — '.$assignment->scope,
                amount: $assignment->approved_cost,
                currencyCode: $assignment->currency_code,
                status: $assignment->status,
            ));
    }
}
