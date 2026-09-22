<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GuestGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_vip' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'guest_group_members')->withPivot(['relationship_label', 'added_at']);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GuestGroupMember::class);
    }
}
