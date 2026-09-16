<?php

namespace App\Models\Concerns;

use App\Models\StatusHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStatusHistory
{
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'subject')->orderByDesc('changed_at');
    }
}
