<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Vendor extends Model implements TracksStatusHistory
{
    /** @use HasFactory<VendorFactory> */
    use HasDocuments, HasFactory, HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(VendorCategory::class, 'vendor_category_vendor');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(VendorServiceArea::class)->orderBy('name');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(VendorAvailability::class)->orderByDesc('starts_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VendorAssignment::class)->orderByDesc('scheduled_starts_at');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(VendorRating::class)->orderByDesc('reviewed_at');
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return ['active' => ['archived'], 'archived' => ['active']];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('display_name', 'like', "%{$search}%")
                ->orWhere('primary_email', 'like', "%{$search}%")
                ->orWhere('primary_phone', 'like', "%{$search}%")
                ->orWhereHas('contacts', fn (Builder $contacts) => $contacts->where('name', 'like', "%{$search}%"))
        ));
    }
}
