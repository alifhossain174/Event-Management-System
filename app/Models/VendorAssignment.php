<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\VendorAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class VendorAssignment extends Model implements TracksStatusHistory
{
    /** @use HasFactory<VendorAssignmentFactory> */
    use HasDocuments, HasFactory, HasStatusHistory;

    public const ACTIVE_STATUSES = ['draft', 'approved', 'in_progress'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_starts_at' => 'immutable_datetime', 'scheduled_ends_at' => 'immutable_datetime',
            'quoted_cost' => 'decimal:4', 'approved_cost' => 'decimal:4',
            'completed_at' => 'immutable_datetime', 'availability_warning' => 'boolean',
            'availability_warning_details' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class, 'vendor_category_id')->withTrashed();
    }

    public function responsibleManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_manager_user_id')->withTrashed();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(VendorWorkOrder::class)->orderByDesc('created_at');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(VendorContract::class)->orderByDesc('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class)->orderByDesc('invoice_date');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(VendorRating::class);
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return [
            'draft' => ['approved', 'cancelled'],
            'approved' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [], 'cancelled' => [],
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('scope', 'like', "%{$search}%")
                ->orWhereHas('vendor', fn (Builder $vendors) => $vendors->where('display_name', 'like', "%{$search}%"))
                ->orWhereHas('event', fn (Builder $events) => $events->where('reference_number', 'like', "%{$search}%")),
        ));
    }
}
