<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventVenueAllocation extends Model
{
    public const ACTIVE_STATUSES = ['planned', 'confirmed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_exclusive' => 'boolean', 'quoted_price' => 'decimal:4',
            'capacity_snapshot' => 'integer', 'capacity_warning' => 'boolean',
            'conflict_override' => 'boolean', 'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(VenueSpace::class, 'venue_space_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(EventVenueAllocationStatusHistory::class)->orderByDesc('changed_at');
    }
}
