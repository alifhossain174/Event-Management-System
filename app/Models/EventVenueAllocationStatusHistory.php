<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventVenueAllocationStatusHistory extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['changed_at' => 'immutable_datetime'];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(EventVenueAllocation::class, 'event_venue_allocation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
