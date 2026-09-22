<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RegistrationForm extends Model
{
    public const DUPLICATE_POLICIES = ['block_email', 'allow'];

    public const CONFIRMATION_CHANNELS = ['none', 'email', 'sms'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approval_required' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RegistrationField::class)->whereNull('archived_at')->orderBy('display_order')->orderBy('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    public function isPublished(): bool
    {
        return $this->is_active && ! $this->archived_at && $this->published_at?->isPast();
    }
}
