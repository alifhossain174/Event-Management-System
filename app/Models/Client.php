<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Client extends Model implements TracksStatusHistory
{
    /** @use HasFactory<ClientFactory> */
    use HasDocuments, HasFactory, HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
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

    public function contactsWithArchived(): HasMany
    {
        return $this->hasMany(ClientContact::class)->withTrashed()->orderByDesc('is_primary')->orderBy('name');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_client_id');
    }

    public function mergedClients(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_client_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id')->withTrashed();
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return [
            'active' => ['archived', 'merged'],
            'archived' => ['active', 'merged'],
            'merged' => [],
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query, string $search) {
            $normalized = mb_strtolower(trim($search));
            $digits = preg_replace('/\D+/', '', $search);

            $query->where(function (Builder $query) use ($search, $normalized, $digits) {
                $query->where('display_name', 'like', "%{$search}%")
                    ->orWhere('organization_name', 'like', "%{$search}%")
                    ->orWhere('primary_email', 'like', "%{$normalized}%")
                    ->orWhere('primary_phone', 'like', "%{$search}%")
                    ->orWhereHas('contacts', fn (Builder $contacts) => $contacts
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$normalized}%")
                        ->when($digits, fn (Builder $contacts) => $contacts->orWhere('normalized_phone', 'like', "%{$digits}%")));
            });
        });
    }
}
