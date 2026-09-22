<?php

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasDocuments, HasFactory;

    public const STATUSES = ['enquiry', 'under_review', 'confirmed', 'waitlisted', 'converted', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_starts_at' => 'immutable_datetime',
            'requested_ends_at' => 'immutable_datetime',
            'budget_estimate' => 'decimal:4',
            'approved_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rescheduled_at' => 'immutable_datetime',
            'financial_review_required' => 'boolean',
            'financial_flags' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'requested_event_category_id')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderByDesc('changed_at');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(BookingChange::class)->orderByDesc('occurred_at');
    }

    public function waitlistEntry(): HasOne
    {
        return $this->hasOne(WaitlistEntry::class);
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class)->orderByDesc('created_at');
    }

    public function reminderSchedules(): HasMany
    {
        return $this->hasMany(ReminderSchedule::class)->orderBy('due_at');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['converted', 'cancelled'], true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('reference_number', 'like', "%{$search}%")
                ->orWhere('venue_preference', 'like', "%{$search}%")
                ->orWhereHas('client', fn (Builder $clients) => $clients->where('display_name', 'like', "%{$search}%")),
        ));
    }
}
