<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class VenueRate extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean'];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(VenueSpace::class, 'venue_space_id');
    }
}
