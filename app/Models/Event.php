<?php

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasDocuments, HasFactory;

    public const STATUSES = ['draft', 'confirmed', 'planning', 'in_progress', 'completed', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'template_snapshot' => 'array',
            'core_budget_estimate' => 'decimal:4',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'ticketing_published_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id')->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class, 'event_template_id');
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_event_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id')->withTrashed();
    }

    public function moduleSettings(): HasMany
    {
        return $this->hasMany(EventModuleSetting::class)->orderBy('module_key');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(EventStatusHistory::class)->orderByDesc('changed_at');
    }

    public function moduleChangeHistory(): HasMany
    {
        return $this->hasMany(EventModuleChangeHistory::class)->orderByDesc('changed_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EventNote::class)->orderByDesc('is_pinned')->orderByDesc('created_at');
    }

    public function timelineItems(): HasMany
    {
        return $this->hasMany(EventTimelineItem::class)->orderByDesc('occurred_at');
    }

    public function venueAllocations(): HasMany
    {
        return $this->hasMany(EventVenueAllocation::class)->orderByDesc('created_at');
    }

    public function vendorAssignments(): HasMany
    {
        return $this->hasMany(VendorAssignment::class)->orderByDesc('scheduled_starts_at');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class)->orderByDesc('scheduled_starts_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderByRaw('due_at IS NULL')->orderBy('due_at');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class)->orderBy('display_name');
    }

    public function guestGroups(): HasMany
    {
        return $this->hasMany(GuestGroup::class)->orderBy('name');
    }

    public function registrationForms(): HasMany
    {
        return $this->hasMany(RegistrationForm::class)->orderBy('name');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class)->orderByDesc('submitted_at');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class)->orderByDesc('issued_at');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderByDesc('issued_at');
    }

    public function budget(): HasOne
    {
        return $this->hasOne(EventBudget::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('received_at');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('due_date');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderByDesc('refunded_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('created_at');
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class)->orderByDesc('created_at');
    }

    public function reminderSchedules(): HasMany
    {
        return $this->hasMany(ReminderSchedule::class)->orderBy('due_at');
    }

    public function isOperationallyReadOnly(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true) || $this->archived_at !== null;
    }

    /** @return list<string> */
    public function enabledModuleKeys(): array
    {
        return $this->moduleSettings()->where('is_enabled', true)->pluck('module_key')->all();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('reference_number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhereHas('client', fn (Builder $clients) => $clients->where('display_name', 'like', "%{$search}%")),
        ));
    }
}
