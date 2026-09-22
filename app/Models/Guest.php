<?php

namespace App\Models;

use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    public const INVITATION_STATUSES = ['not_invited', 'issued', 'sent', 'revoked'];

    public const RSVP_STATUSES = ['pending', 'accepted', 'declined', 'tentative'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'plus_one_limit' => 'integer',
            'invited_party_size' => 'integer',
            'confirmed_party_size' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function groupMembership(): HasOne
    {
        return $this->hasOne(GuestGroupMember::class);
    }

    public function group(): BelongsToMany
    {
        return $this->belongsToMany(GuestGroup::class, 'guest_group_members')->withPivot(['relationship_label', 'added_at'])->limit(1);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class)->orderByDesc('issued_at');
    }

    public function activeInvitation(): HasOne
    {
        return $this->hasOne(Invitation::class)->whereNot('status', 'revoked')->latestOfMany('issued_at');
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }

    public function seatAssignment(): HasOne
    {
        return $this->hasOne(SeatAssignment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(GuestNote::class)->whereNull('archived_at')->orderByDesc('created_at');
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(GuestCheckIn::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = mb_strtolower(trim((string) $search));

        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
            $query->where('normalized_name', 'like', "%{$search}%")
                ->orWhere('normalized_email', 'like', "%{$search}%")
                ->orWhere('normalized_phone', 'like', '%'.preg_replace('/\D+/', '', $search).'%')
                ->orWhere('external_reference', 'like', "%{$search}%");
        }));
    }
}
