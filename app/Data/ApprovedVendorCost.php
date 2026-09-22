<?php

namespace App\Data;

final readonly class ApprovedVendorCost
{
    public function __construct(
        public int $sourceId,
        public int $eventId,
        public int $vendorId,
        public string $description,
        public string $amount,
        public string $currencyCode,
        public string $status,
    ) {}
}
