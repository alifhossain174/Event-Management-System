<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PromoCode extends Model
{
    public const TYPES = ['percentage', 'fixed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:4', 'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime', 'usage_limit' => 'integer',
            'is_active' => 'boolean', 'archived_at' => 'immutable_datetime',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->where('is_active', true);
    }
}
