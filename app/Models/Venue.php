<?php

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasDocuments, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer', 'parking_capacity' => 'integer',
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(VenueSpace::class)->orderBy('name');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(VenueFacility::class)->orderBy('name');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(VenueRate::class)->orderBy('label');
    }

    public function seatingPlans(): HasMany
    {
        return $this->hasMany(SeatingPlan::class)->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(VenueMedia::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(EventVenueAllocation::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('address_line_1', 'like', "%{$search}%"),
        ));
    }
}
