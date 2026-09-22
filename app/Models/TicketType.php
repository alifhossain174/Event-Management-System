<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TicketType extends Model
{
    public const CATEGORIES = ['vip', 'regular', 'early_bird', 'custom'];

    public const STATUSES = ['draft', 'on_sale', 'paused', 'sold_out', 'archived'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer', 'price' => 'decimal:4', 'is_public' => 'boolean',
            'sale_starts_at' => 'immutable_datetime', 'sale_ends_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function activeIssuedCount(): int
    {
        return $this->tickets()->whereIn('status', ['issued', 'used'])->count();
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity_total - $this->activeIssuedCount());
    }
}
