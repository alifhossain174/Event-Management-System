<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class CalendarEntry
{
    public function __construct(
        public string $sourceType,
        public int $sourceId,
        public string $title,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public string $url,
        public string $context,
        public ?int $branchId = null,
    ) {}

    public function key(): string
    {
        return "{$this->sourceType}:{$this->sourceId}";
    }
}
