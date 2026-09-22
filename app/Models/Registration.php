<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Registration extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'confirmation_sent_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')->withTrashed();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RegistrationResponse::class)->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RegistrationStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
            $query->where('reference_number', 'like', "%{$search}%")
                ->orWhere('registrant_name', 'like', "%{$search}%")
                ->orWhere('normalized_email', 'like', '%'.mb_strtolower($search).'%')
                ->orWhere('registrant_phone', 'like', "%{$search}%");
        }));
    }
}
