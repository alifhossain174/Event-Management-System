<?php

namespace App\Contracts;

interface TracksStatusHistory
{
    public function statusHistoryColumn(): string;

    /** @return array<string, list<string>> */
    public function allowedStatusTransitions(): array;
}
