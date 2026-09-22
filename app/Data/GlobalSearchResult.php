<?php

namespace App\Data;

final readonly class GlobalSearchResult
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $subtitle = null,
        public ?string $status = null,
    ) {}
}
