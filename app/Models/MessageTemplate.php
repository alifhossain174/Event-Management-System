<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MessageTemplate extends Model
{
    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    public const CATEGORIES = ['operational', 'marketing'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }
}
